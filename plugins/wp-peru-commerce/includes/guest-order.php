<?php
/**
 * IMP-31 — R8-09, proyección exclusiva del pedido de la cookie invitada.
 *
 * No reutilizar AdminOrder ni introducir consulta arbitraria de order_id.
 * El total y los snapshots históricos se comprueban bajo lock de pedido,
 * sesión y enlace fuente; no se devuelven contactos, direcciones, archivos,
 * cuentas MP, notas de operador, movimientos ni referencias de pago.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Order
{
    /** @return array<string,mixed> GuestOrder OpenAPI */
    public static function detail(): array
    {
        Guest_Sessions::sameOrigin('GET');
        $result=Transaction::run(static function(Transaction_Context $tx): array {
            $session=Guest_Sessions::authorizeReadWithin($tx);
            $orderId=$session['order_id'];
            $locked=$tx->lockedOrder($orderId);
            $rows=$tx->rows(
                'SELECT id,order_number,status,payment_method,currency,'
                    . 'subtotal_cents,delivery_cents,total_cents,created_at,'
                    . 'confirmed_at,policy_version_id,privacy_version_id,'
                    . 'original_delivery_snapshot,row_version '
                    . 'FROM %i WHERE id=%s',
                [Orders_Domain::table('pc_orders'),$orderId]
            );
            if (1!==\count($rows)) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $order=$rows[0];
            if ($order['status']!==$locked['status']
                || $order['payment_method']!==$locked['payment_method']
                || (string)$order['row_version']!==(string)$locked['row_version']
                || 'PEN'!==$order['currency']) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $total=Money::fromApi((string)$order['total_cents']);
            $subtotal=Money::fromApi((string)$order['subtotal_cents']);
            $fee=Money::fromApi((string)$order['delivery_cents']);
            if ($total<1 || $total!==Money::addChecked($subtotal,$fee)) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $lines=$tx->rows(
                'SELECT id,order_id,sellable_id,kind,quantity,'
                    . 'unit_price_cents,subtotal_cents,fulfillment_state,'
                    . 'catalog_snapshot FROM %i WHERE order_id=%s '
                    . 'ORDER BY id ASC LIMIT 101',
                [Orders_Domain::table('pc_order_lines'),$orderId]
            );
            if ([]===$lines || \count($lines)>100) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $committed=$tx->rows(
                'SELECT s.order_line_id,s.sellable_id,s.committed_units,'
                    . 's.fulfilled_units,s.released_units FROM %i s '
                    . 'INNER JOIN %i l ON l.id=s.order_line_id '
                    . 'WHERE l.order_id=%s LIMIT 101',
                [Orders_Domain::table('pc_stock_commitments'),
                    Orders_Domain::table('pc_order_lines'),$orderId]
            );
            if (\count($committed)>100) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $physicalByLine=[];
            foreach ($committed as $c) {
                $lineId=Domain_Validation::sqlId((string)$c['order_line_id']);
                if (isset($physicalByLine[$lineId])) {
                    throw new Transaction_Failure('pc_guest_order_invalid');
                }
                $physicalByLine[$lineId]=$c;
            }
            $sum=0;
            $mapped=[];
            $serviceLine=null;
            $countPhysical=0;
            foreach ($lines as $line) {
                $lineId=Domain_Validation::sqlId((string)$line['id']);
                $kind=Domain_Validation::sellableKind((string)$line['kind']);
                $quantity=Orders_Domain::quantity((string)$line['quantity']);
                $unit=Money::fromApi((string)$line['unit_price_cents']);
                if ($quantity<1 || $unit<1
                    || Money::lineSubtotal($unit,$quantity)!==
                        Money::fromApi((string)$line['subtotal_cents'])) {
                    throw new Transaction_Failure('pc_guest_order_invalid');
                }
                $sum=Money::addChecked($sum,
                    Money::fromApi((string)$line['subtotal_cents']));
                $snapshot=Orders_Domain::catalogSnapshot(
                    (string)$line['catalog_snapshot'],$kind
                );
                $item=[
                    'line_id'=>$lineId,
                    'title'=>$snapshot['title'],
                    'kind'=>$kind,
                    'quantity'=>$quantity,
                    'unit_price_cents'=>Money::toApi($unit),
                    'fulfillment_state'=>Orders_Domain::lineState(
                        $kind,(string)$line['fulfillment_state']
                    ),
                ];
                if ('physical'===$kind) {
                    ++$countPhysical;
                    $item['physical_quantities']=Orders_Domain::physicalQuantities(
                        $order,$line,$physicalByLine[$lineId]??null
                    );
                    unset($physicalByLine[$lineId]);
                } elseif (isset($physicalByLine[$lineId])) {
                    throw new Transaction_Failure('pc_guest_order_invalid');
                }
                if ('service'===$kind) {
                    if (null!==$serviceLine || 1!==$quantity) {
                        throw new Transaction_Failure('pc_guest_order_invalid');
                    }
                    $serviceLine=$lineId;
                }
                $mapped[]=$item;
            }
            if ([]!==$physicalByLine || $sum!==$subtotal) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $policyId=Domain_Validation::sqlId((string)$order['policy_version_id']);
            $privacyId=Domain_Validation::sqlId((string)$order['privacy_version_id']);
            $notices=[
                'policy_version'=>$policyId,
                'privacy_version'=>$privacyId,
                'policy_notice'=>Settings_Store::policyWithin(
                    $tx,$policyId,'commercial_terms'
                ),
                'privacy_notice'=>Settings_Store::policyWithin(
                    $tx,$privacyId,'privacy_notice'
                ),
            ];
            $orderNumber=(string)$order['order_number'];
            if (!\preg_match('/\APC-[a-f0-9]{24}\z/D',$orderNumber)) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }
            $data=[
                'order_number'=>$orderNumber,
                'created_at'=>Business_Time::toApi(
                    Business_Time::fromDatabase((string)$order['created_at'])
                ),
                'order_state'=>Orders_Domain::state((string)$order['status']),
                'currency'=>'PEN',
                'lines'=>$mapped,
                'total_cents'=>Money::toApi($total),
                'payment_summary'=>self::paymentWithin($tx,$orderId,$order,$total),
                'agreed_notices'=>$notices,
            ];

            // Resumen logístico sin copiar direcciones, teléfono, nombres,
            // geo o notas del comerciante.
            if ($countPhysical>0) {
                $delivery=Orders_Domain::deliverySnapshot(
                    $order['original_delivery_snapshot']
                );
                if (null===$delivery
                    || !\in_array($delivery['mode'],['delivery','pickup'],true)) {
                    throw new Transaction_Failure('pc_guest_order_invalid');
                }
                $data['delivery_summary']='pickup'===$delivery['mode']
                    ? 'Recojo en el local'
                    : 'Entrega a domicilio';
            } elseif ($fee>0) {
                throw new Transaction_Failure('pc_guest_order_invalid');
            }

            if (null!==$serviceLine) {
                $appointments=$tx->rows(
                    'SELECT order_line_id,starts_at_utc,cancelled_at,'
                        . 'completed_at,no_show_at FROM %i '
                        . 'WHERE order_id=%s LIMIT 2',
                    [Orders_Domain::table('pc_appointments'),$orderId]
                );
                if (\count($appointments)>1
                    || []!==$appointments
                        && (string)$appointments[0]['order_line_id']!==$serviceLine) {
                    throw new Transaction_Failure('pc_guest_order_invalid');
                }
                if ([]===$appointments) {
                    $data['appointment_summary']='Servicio pendiente de confirmación';
                } else {
                    $a=$appointments[0];
                    $state=null!==$a['cancelled_at'] ? 'Cita cancelada'
                        : (null!==$a['completed_at'] ? 'Servicio realizado'
                            : (null!==$a['no_show_at'] ? 'No asistencia registrada'
                                : 'Cita programada'));
                    $data['appointment_summary']=$state.' — '.
                        Business_Time::toApi(
                            Business_Time::fromDatabase((string)$a['starts_at_utc'])
                        );
                }
            }
            return ['data'=>$data,'session'=>$session];
        });
        Guest_Sessions::refreshCookie($result['session']);
        return $result['data'];
    }

    /** Dinero sólo de asientos históricos del pedido propio, sin refs. */
    private static function paymentWithin(
        Transaction_Context $tx,string $orderId,array $order,int $total
    ): array {
        $events=$tx->rows(
            'SELECT kind,provider,amount_cents,payment_attempt_id '
                . 'FROM %i WHERE order_id=%s ORDER BY id ASC LIMIT 201',
            [Orders_Domain::table('pc_payment_events'),$orderId]
        );
        if (\count($events)>200) {
            throw new Transaction_Failure('pc_guest_order_invalid');
        }
        if ([]===$events) {
            return [
                'status_label'=>'cash'===$order['payment_method']
                    ? 'PENDIENTE_COBRO' : 'PENDIENTE_PAGO',
                'amount_paid_cents'=>'0',
                'amount_outstanding_cents'=>Money::toApi($total),
            ];
        }
        // Solo el cobro inicial positivo respaldado por el intento
        // APROBADO (MP) o asiento legítimo cash puede mostrarse como
        // acreditado. Ajustes/reembolsos/contracargos se etiquetan como
        // CONCILIACION_PENDIENTE hasta tener una proyección financiera
        // completa, no se calculan balances inventados.
        if (1!==\count($events)
            || 'initial_charge'!==$events[0]['kind']
            || $events[0]['provider']!==$order['payment_method']
            || (string)$events[0]['amount_cents']!==(string)$total) {
            return ['status_label'=>'CONCILIACION_PENDIENTE'];
        }
        if ('mercado_pago'===$order['payment_method']) {
            if (null===$events[0]['payment_attempt_id']) {
                return ['status_label'=>'CONCILIACION_PENDIENTE'];
            }
            $attempts=$tx->rows(
                'SELECT state FROM %i WHERE id=%s AND order_id=%s '
                    . 'AND provider=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_attempts'),
                    (string)$events[0]['payment_attempt_id'],
                    $orderId,'mercado_pago']
            );
            if (1!==\count($attempts) || 'APROBADO'!==$attempts[0]['state']) {
                return ['status_label'=>'CONCILIACION_PENDIENTE'];
            }
        } elseif ('cash'!==$order['payment_method']) {
            return ['status_label'=>'CONCILIACION_PENDIENTE'];
        }
        return [
            'status_label'=>\in_array((string)$order['status'],
                ['PENDIENTE_PAGO','VENCIDO'],true)
                ? 'ACREDITADO_EN_CONCILIACION' : 'ACREDITADO',
            'amount_paid_cents'=>Money::toApi($total),
            'amount_outstanding_cents'=>'0',
        ];
    }
}
