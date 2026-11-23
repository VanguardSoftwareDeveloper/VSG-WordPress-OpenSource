<?php
/**
 * IMP-45 — R8-38/R8-54: reprogramación de la cita existente y resultado.
 *
 * Una única fila pc_appointments por pedido. El slot nuevo se comprueba
 * bajo el mismo ancla de recurso y TODOS los días (viejos/nuevos) antes de
 * moverlo; cualquier error revierte horarios, historia y avisos juntos.
 *
 * El resultado documental requiere A y evidencia opaca, y no mueve dinero,
 * derechos ni existencias. La constancia de aviso es una intención duradera,
 * no la afirmación de que el correo haya sido recibido.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Appointment_Operations
{
    private const RESCHEDULE = 'appointment_reschedule';
    private const OUTCOME = 'appointment_outcome';
    private const OUTCOMES = ['REALIZADA','NO_ASISTIO'];

    public static function guardReschedule(array $ctx, ?string $role): array
    {
        return self::guard($ctx,$role,false);
    }

    public static function guardOutcome(array $ctx, ?string $role): array
    {
        return self::guard($ctx,$role,true);
    }

    private static function guard(array $ctx, ?string $role, bool $outcome): array
    {
        if (!self::ready($role,$outcome)) { return []; }
        try {
            $id = Domain_Validation::sqlId($ctx['path']['id'] ?? '');
            self::input($ctx['body'] ?? null,$outcome);
            $found = Transaction::run(static function (Transaction_Context $tx)
                use ($id): bool {
                $rows = $tx->rows(
                    'SELECT a.id FROM %i a INNER JOIN %i l '
                        . 'ON l.id=a.order_line_id AND l.order_id=a.order_id '
                        . 'INNER JOIN %i o ON o.id=a.order_id '
                        . 'WHERE a.id=%s AND a.resource_id=%s AND l.kind=%s LIMIT 2',
                    [Agenda_Repository::table('pc_appointments'),
                        Orders_Domain::table('pc_order_lines'),
                        Orders_Domain::table('pc_orders'),
                        $id,Agenda_Time::RESOURCE,'service']
                );
                return 1===\count($rows);
            });
            return $found ? [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,
            ] : [];
        } catch (\Throwable $ignored) { return []; }
    }

    /** @return array{status:int,body:array<string,string>} */
    public static function reschedule(string $appointmentId,array $body): array
    {
        self::requireStaff(false);
        $appointmentId = Domain_Validation::sqlId($appointmentId);
        $body = self::input($body,false);
        $start = Business_Time::fromApi($body['new_start']);
        $end = Business_Time::fromApi($body['new_end']);
        self::minuteInterval($start,$end);
        $scope = self::scope($appointmentId,self::RESCHEDULE);
        $payload = $body;
        unset($payload['idempotency_key']);
        $actor = Event_Actor::staff(['perucommerce_appointments_manage']);

        return Transaction::run(static function (Transaction_Context $tx) use (
            $appointmentId,$body,$start,$end,$scope,$payload,$actor
        ): array {
            $claim = Idempotency::claimWithin(
                $tx,$scope,self::RESCHEDULE,$body['idempotency_key'],$payload
            );
            self::claimed($claim);
            $loc = self::locateWithin($tx,$appointmentId);
            $orderId = $loc['order_id'];
            $lineId = $loc['order_line_id'];
            $order = $tx->lockOrder($orderId);
            $line = $tx->lockOrderLine($orderId,$lineId);
            if ('service' !== $line['kind']
                || $line['order_id'] !== $orderId) {
                throw new Transaction_Failure('pc_appointment_denied');
            }
            if ('replay' === $claim['status']) {
                return [
                    'status'=>200,
                    'body'=>self::replayWithin(
                        $tx,$claim,$appointmentId,self::RESCHEDULE,$body
                    ),
                ];
            }
            if ('CONFIRMADO' !== $order['status']
                || 'CONFIRMADA' !== $line['fulfillment_state']) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $tx->lockSellables([(string)$line['sellable_id']]);
            $current = self::locateWithin($tx,$appointmentId);
            $service = Checkout_Appointments::fromOrderLineWithin(
                $tx,$orderId,$lineId
            );
            $oldStart = Business_Time::fromDatabase($current['starts_at_utc']);
            $oldEnd = Business_Time::fromDatabase($current['ends_at_utc']);
            $initialStart = Business_Time::fromApi($service['slot']['starts_at_utc']);
            $initialEnd = Business_Time::fromApi($service['slot']['ends_at_utc']);
            $originalDuration = $initialEnd->getTimestamp()-$initialStart->getTimestamp();
            if ($originalDuration < 60 || $originalDuration > 86400
                || 0 !== $originalDuration % 60
                || $oldEnd->getTimestamp()-$oldStart->getTimestamp() !== $originalDuration
                || $end->getTimestamp()-$start->getTimestamp() !== $originalDuration
                || ($start == $oldStart && $end == $oldEnd)) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $anchor = $tx->lockResource(Agenda_Time::RESOURCE);
            $zone = (string)$anchor['timezone'];
            if ($current['local_timezone'] !== $zone
                || $service['slot']['timezone'] !== $zone) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            [$oldFrom,$oldTo] = self::buffered(
                $oldStart,$oldEnd,$service
            );
            [$newFrom,$newTo] = self::buffered($start,$end,$service);
            $days = \array_values(\array_unique(\array_merge(
                Transaction_Context::datesTouched($oldFrom,$oldTo,$zone),
                Transaction_Context::datesTouched($newFrom,$newTo,$zone)
            )));
            if ([] === $days || \count($days)>12) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $tx->lockCalendarDays(Agenda_Time::RESOURCE,$days);
            $locked = $tx->lockAppointmentForOrder($orderId);
            if (null === $locked
                || (string)$locked['id'] !== $appointmentId
                || (string)$locked['order_line_id'] !== $lineId
                || (string)$locked['resource_id'] !== Agenda_Time::RESOURCE
                || (string)$locked['row_version'] !== $body['expected_version']
                || (string)$locked['starts_at_utc'] !== $current['starts_at_utc']
                || (string)$locked['ends_at_utc'] !== $current['ends_at_utc']) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            self::stillConfirmedWithin($tx,$appointmentId,$orderId,$lineId);
            $clock = self::nowWithin($tx);
            if (Business_Time::toDatabase($start) <= $clock
                || $current['starts_at_utc'] <= $clock) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            self::fitsScheduleWithin(
                $tx,$anchor,$zone,$start,$end,$newFrom,$newTo,$service
            );
            if ([]!==Agenda_Repository::busyWithin(
                $tx,$newFrom,$newTo,null,$appointmentId
            )) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $next = $tx->advanceVersion(
                'pc_appointments',$appointmentId,$body['expected_version']
            );
            $newStart=Business_Time::toDatabase($start);
            $newEnd=Business_Time::toDatabase($end);
            if (1!==$tx->execute(
                'UPDATE %i SET starts_at_utc=%s,ends_at_utc=%s '
                    . 'WHERE id=%s AND order_id=%s AND order_line_id=%s '
                    . 'AND resource_id=%s AND cancelled_at IS NULL '
                    . 'AND completed_at IS NULL AND no_show_at IS NULL '
                    . 'AND row_version=%s',
                [Agenda_Repository::table('pc_appointments'),$newStart,$newEnd,
                    $appointmentId,$orderId,$lineId,Agenda_Time::RESOURCE,$next]
            )) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            // Invalidar la versión de línea/pedido usada por una acción
            // concurrente o por un panel que aún muestre el turno anterior.
            $tx->advanceVersion(
                'pc_order_lines',$lineId,(string)$line['row_version']
            );
            $tx->advanceVersion(
                'pc_orders',$orderId,(string)$order['row_version']
            );
            // Outbox + historia en el mismo COMMIT; no se envía correo aquí.
            $notice=Queue_Outbox::serviceRescheduledWithin(
                $tx,$claim['operation_id'],$appointmentId,$orderId,
                $lineId,$newStart,$newEnd
            );
            Event_Journal::appointmentWithin(
                $tx,$actor,$claim['operation_id'],$appointmentId,
                $current['starts_at_utc'],$current['ends_at_utc'],
                $newStart,$newEnd,'staff_reschedule',$notice['email_id']
            );
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'appointment',$appointmentId,
                'reschedule_reason_recorded','staff_reschedule',
                \hash('sha256',$body['reason']),
                \hash('sha256',$newStart.':'.$newEnd.':'.$next)
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'appointment',$appointmentId,200
            );
            return [
                'status'=>200,'body'=>[
                    'operation_id'=>$claim['operation_id'],
                    'entity_id'=>$appointmentId,
                    'row_version'=>$next,
                    'result'=>'appointment_rescheduled',
                    'public_status'=>'CONFIRMADA',
                ],
            ];
        },true);
    }

    /** @return array{status:int,body:array<string,string>} */
    public static function outcome(string $appointmentId,array $body): array
    {
        self::requireStaff(true);
        $appointmentId=Domain_Validation::sqlId($appointmentId);
        $body=self::input($body,true);
        $observed=Business_Time::fromApi($body['observed_at_utc']);
        $scope=self::scope($appointmentId,self::OUTCOME);
        $payload=$body;
        unset($payload['idempotency_key']);
        $actor=Event_Actor::staff(['perucommerce_appointments_manage']);
        return Transaction::run(static function(Transaction_Context $tx) use (
            $appointmentId,$body,$observed,$scope,$payload,$actor
        ): array {
            $claim=Idempotency::claimWithin(
                $tx,$scope,self::OUTCOME,$body['idempotency_key'],$payload
            );
            self::claimed($claim);
            $loc=self::locateWithin($tx,$appointmentId);
            $orderId=$loc['order_id'];
            $lineId=$loc['order_line_id'];
            $order=$tx->lockOrder($orderId);
            $line=$tx->lockOrderLine($orderId,$lineId);
            if ('service'!==$line['kind'] || $line['order_id']!==$orderId) {
                throw new Transaction_Failure('pc_appointment_denied');
            }
            if ('replay'===$claim['status']) {
                return ['status'=>200,'body'=>self::replayWithin(
                    $tx,$claim,$appointmentId,self::OUTCOME,$body
                )];
            }
            if ('CONFIRMADO'!==$order['status']
                || 'CONFIRMADA'!==$line['fulfillment_state']) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $tx->lockSellables([(string)$line['sellable_id']]);
            $current=self::locateWithin($tx,$appointmentId);
            $service=Checkout_Appointments::fromOrderLineWithin(
                $tx,$orderId,$lineId
            );
            $anchor=$tx->lockResource(Agenda_Time::RESOURCE);
            $zone=(string)$anchor['timezone'];
            if ($current['local_timezone']!==$zone
                || $service['slot']['timezone']!==$zone) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $oldStart=Business_Time::fromDatabase($current['starts_at_utc']);
            $oldEnd=Business_Time::fromDatabase($current['ends_at_utc']);
            $contractStart=Business_Time::fromApi($service['slot']['starts_at_utc']);
            $contractEnd=Business_Time::fromApi($service['slot']['ends_at_utc']);
            $duration=$contractEnd->getTimestamp()-$contractStart->getTimestamp();
            if ($duration<60 || $duration>86400 || 0!==$duration%60
                || $oldEnd->getTimestamp()-$oldStart->getTimestamp()!==$duration) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            [$from,$to]=self::buffered($oldStart,$oldEnd,$service);
            $days=Transaction_Context::datesTouched($from,$to,$zone);
            if ([]===$days || \count($days)>12) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $tx->lockCalendarDays(Agenda_Time::RESOURCE,$days);
            $locked=$tx->lockAppointmentForOrder($orderId);
            if (null===$locked
                || (string)$locked['id']!==$appointmentId
                || (string)$locked['order_line_id']!==$lineId
                || (string)$locked['resource_id']!==Agenda_Time::RESOURCE
                || (string)$locked['row_version']!==$body['expected_version']
                || (string)$locked['starts_at_utc']!==$current['starts_at_utc']
                || (string)$locked['ends_at_utc']!==$current['ends_at_utc']) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            self::stillConfirmedWithin($tx,$appointmentId,$orderId,$lineId);
            $now=self::nowWithin($tx);
            $observedDb=Business_Time::toDatabase($observed);
            if ($observedDb>$now || $observedDb<$current['ends_at_utc']) {
                // NO_ASISTIO se constata una vez terminada la ventana, no
                // se programa anticipadamente ni se simula REALIZADA.
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $next=$tx->advanceVersion(
                'pc_appointments',$appointmentId,$body['expected_version']
            );
            $field='REALIZADA'===$body['outcome']
                ? 'completed_at' : 'no_show_at';
            // El nombre SQL proviene exclusivamente del enum verificado.
            if (1!==$tx->execute(
                'UPDATE %i SET '.$field.'=%s,outcome_evidence_ref=%s,'
                    . 'outcome_recorded_by=%s,outcome_recorded_at=UTC_TIMESTAMP(6) '
                    . 'WHERE id=%s AND order_id=%s AND order_line_id=%s '
                    . 'AND cancelled_at IS NULL AND completed_at IS NULL '
                    . 'AND no_show_at IS NULL AND row_version=%s',
                [Agenda_Repository::table('pc_appointments'),
                    $observedDb,$body['evidence_ref'],
                    (string)\get_current_user_id(),$appointmentId,
                    $orderId,$lineId,$next]
            )) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $lineNext=$tx->advanceVersion(
                'pc_order_lines',$lineId,(string)$line['row_version']
            );
            if (1!==$tx->execute(
                'UPDATE %i SET fulfillment_state=%s WHERE id=%s '
                    . 'AND order_id=%s AND kind=%s '
                    . 'AND fulfillment_state=%s AND row_version=%s',
                [Orders_Domain::table('pc_order_lines'),$body['outcome'],
                    $lineId,$orderId,'service','CONFIRMADA',$lineNext]
            )) {
                throw new Transaction_Failure('pc_appointment_conflict');
            }
            $tx->advanceVersion(
                'pc_orders',$orderId,(string)$order['row_version']
            );
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'appointment',$appointmentId,
                'appointment_outcome_recorded','service_observation',
                \hash('sha256','CONFIRMADA:'.$body['expected_version']),
                \hash('sha256',$body['outcome'].':'.$observedDb.':'
                    .\hash('sha256',$body['evidence_ref']).':'.$next)
            );
            // Aviso PANEL, no penalidad, devolución ni email con detalles.
            Queue_Outbox::serviceOutcomeWithin(
                $tx,$claim['operation_id'],$appointmentId,$orderId,
                $lineId,$body['outcome']
            );
            Orders_Finalization::afterWithin(
                $tx,$actor,$claim['operation_id'],$orderId
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'appointment',$appointmentId,200
            );
            return ['status'=>200,'body'=>[
                'operation_id'=>$claim['operation_id'],
                'result'=>'service_outcome_recorded',
                'row_version'=>$next,
            ]];
        },true);
    }

    /** Consulta inicial sin privilegios; la pertenencia y versión se
     * revalidan después de tomar locks en el orden E-05. */
    private static function locateWithin(
        Transaction_Context $tx,string $id
    ): array {
        $rows=$tx->rows(
            'SELECT a.id,a.order_id,a.order_line_id,a.starts_at_utc,'
                . 'a.ends_at_utc,a.local_timezone,a.row_version '
                . 'FROM %i a INNER JOIN %i l '
                . 'ON l.id=a.order_line_id AND l.order_id=a.order_id '
                . 'INNER JOIN %i o ON o.id=a.order_id '
                . 'WHERE a.id=%s AND a.resource_id=%s AND l.kind=%s LIMIT 2',
            [Agenda_Repository::table('pc_appointments'),
                Orders_Domain::table('pc_order_lines'),
                Orders_Domain::table('pc_orders'),$id,
                Agenda_Time::RESOURCE,'service']
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_appointment_denied');
        }
        $out=$rows[0];
        $out['order_id']=Domain_Validation::sqlId((string)$out['order_id']);
        $out['order_line_id']=Domain_Validation::sqlId((string)$out['order_line_id']);
        $out['row_version']=Domain_Validation::sqlId((string)$out['row_version']);
        return $out;
    }

    private static function stillConfirmedWithin(
        Transaction_Context $tx,string $appointmentId,
        string $orderId,string $lineId
    ): void {
        $rows=$tx->rows(
            'SELECT a.cancelled_at,a.completed_at,a.no_show_at,'
                . 'a.outcome_recorded_at,l.fulfillment_state,o.status '
                . 'FROM %i a INNER JOIN %i l ON l.id=a.order_line_id '
                . 'AND l.order_id=a.order_id '
                . 'INNER JOIN %i o ON o.id=a.order_id '
                . 'WHERE a.id=%s AND a.order_id=%s AND a.order_line_id=%s '
                . 'FOR UPDATE',
            [Agenda_Repository::table('pc_appointments'),
                Orders_Domain::table('pc_order_lines'),
                Orders_Domain::table('pc_orders'),
                $appointmentId,$orderId,$lineId]
        );
        if (1!==\count($rows)
            || null!==$rows[0]['cancelled_at']
            || null!==$rows[0]['completed_at']
            || null!==$rows[0]['no_show_at']
            || null!==$rows[0]['outcome_recorded_at']
            || 'CONFIRMADA'!==$rows[0]['fulfillment_state']
            || 'CONFIRMADO'!==$rows[0]['status']) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
    }

    private static function fitsScheduleWithin(
        Transaction_Context $tx,array $anchor,string $zone,
        \DateTimeImmutable $start,\DateTimeImmutable $end,
        \DateTimeImmutable $occupiedStart,\DateTimeImmutable $occupiedEnd,
        array $service
    ): void {
        $date=Business_Time::dateInBusinessZone($start,$zone);
        $previous=Agenda_Time::day($date,-1);
        $scheduleToday=Agenda_Repository::scheduleWithin($tx,$date,$anchor);
        $schedulePrevious=Agenda_Repository::scheduleWithin($tx,$previous,$anchor);
        $duration=$end->getTimestamp()-$start->getTimestamp();
        $step=$duration+60*($service['before_minutes']+$service['after_minutes']);
        if ($step<60) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
        $matches=0;
        foreach ([[$previous,$schedulePrevious],[$date,$scheduleToday]] as [$d,$schedule]) {
            foreach (Agenda_Time::windows($schedule['weekly_rules'],$d,$zone)
                as [$open,$close]) {
                $candidate=Agenda_Time::addMinutes(
                    $open,$service['before_minutes']
                );
                $delta=$start->getTimestamp()-$candidate->getTimestamp();
                if ($delta>=0 && 0===$delta%$step
                    && $occupiedStart>=$open && $occupiedEnd<=$close) {
                    ++$matches;
                }
            }
        }
        if (1!==$matches) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
    }

    /** @return array{0:\DateTimeImmutable,1:\DateTimeImmutable} */
    private static function buffered(
        \DateTimeImmutable $start,\DateTimeImmutable $end,array $service
    ): array {
        $before=$service['before_minutes'] ?? null;
        $after=$service['after_minutes'] ?? null;
        if (!\is_int($before) || !\is_int($after)
            || $before<0 || $before>1440 || $after<0 || $after>1440) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
        return [
            Agenda_Time::addMinutes($start,-$before),
            Agenda_Time::addMinutes($end,$after),
        ];
    }

    private static function minuteInterval(
        \DateTimeImmutable $start,\DateTimeImmutable $end
    ): void {
        if ($start->format('s.u')!=='00.000000'
            || $end->format('s.u')!=='00.000000'
            || $start >= $end
            || $end->getTimestamp()-$start->getTimestamp()>86400) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
    }

    private static function nowWithin(Transaction_Context $tx): string
    {
        $rows=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
        if (1!==\count($rows) || !\is_string($rows[0]['now_utc'])) {
            throw new Transaction_Failure('pc_appointment_invalid');
        }
        return $rows[0]['now_utc'];
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $appointmentId,
        string $action,array $body
    ): array {
        if ('appointment'!==($claim['entity_type']??null)
            || $appointmentId!==($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
        $code=self::RESCHEDULE===$action
            ? 'appointment_rescheduled' : 'appointment_outcome_recorded';
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                . 'AND operation_code=%s AND actor_kind=%s '
                . 'AND actor_user_id=%s AND request_id=UNHEX(%s) LIMIT 2',
            [Orders_Domain::table('pc_audit_log'),
                'appointment',$appointmentId,$code,
                'staff',(string)\get_current_user_id(),
                Event_Journal::correlationHex($claim['operation_id'])]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
        $version=Settings_Store::nextVersion($body['expected_version']);
        if (self::RESCHEDULE===$action) {
            return [
                'operation_id'=>$claim['operation_id'],
                'entity_id'=>$appointmentId,
                'row_version'=>$version,
                'result'=>'already_applied',
                'public_status'=>'CONFIRMADA',
            ];
        }
        return [
            'operation_id'=>$claim['operation_id'],
            'result'=>'already_applied',
            'row_version'=>$version,
        ];
    }

    private static function claimed(array $claim): void
    {
        if (!\in_array($claim['status']??null,['new','replay'],true)) {
            throw new Transaction_Failure('pc_appointment_conflict');
        }
    }

    private static function input(mixed $body,bool $outcome): array
    {
        $expected=$outcome
            ? ['outcome','observed_at_utc','evidence_ref',
                'expected_version','idempotency_key']
            : ['new_start','new_end','reason',
                'expected_version','idempotency_key'];
        if (!\is_array($body) || \array_is_list($body)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $keys=\array_keys($body);
        \sort($keys,\SORT_STRING);
        \sort($expected,\SORT_STRING);
        if ($keys!==$expected) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        if (!\is_string($body['expected_version'])) {
            throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
        }
        try { Domain_Validation::sqlId($body['expected_version']); }
        catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
        }
        if (!\is_string($body['idempotency_key'])
            || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
            || \mb_strlen($body['idempotency_key'],'UTF-8')<16
            || \mb_strlen($body['idempotency_key'],'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/u',$body['idempotency_key'])) {
            throw new Rest_Failure('pc_field_invalid',422,false,'idempotency_key');
        }
        if ($outcome) {
            if (!\in_array($body['outcome'],self::OUTCOMES,true)
                || !\is_string($body['observed_at_utc'])) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
            self::text($body['evidence_ref'],500,'evidence_ref',true);
            try { Business_Time::fromApi($body['observed_at_utc']); }
            catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422,false,'observed_at_utc');
            }
        } else {
            if (!\is_string($body['new_start'])
                || !\is_string($body['new_end'])) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
            self::text($body['reason'],1000,'reason',false);
            try {
                $start=Business_Time::fromApi($body['new_start']);
                $end=Business_Time::fromApi($body['new_end']);
                self::minuteInterval($start,$end);
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
        }
        return $body;
    }

    private static function text(
        mixed $value,int $max,string $field,bool $opaque
    ): void {
        if (!\is_string($value) || ''===\trim($value)
            || !\mb_check_encoding($value,'UTF-8')
            || \mb_strlen($value,'UTF-8')>$max
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)
            || $opaque && (\str_contains($value,'://')
                || \preg_match('/[\x00-\x1F\x7F]/u',$value))) {
            throw new Rest_Failure('pc_field_invalid',422,false,$field);
        }
    }

    private static function ready(?string $role,bool $outcome): bool
    {
        return \in_array($role,$outcome?['A']:['A','O'],true)
            && Access_Roles::currentRole()===$role
            && \current_user_can('perucommerce_appointments_manage')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin())
            && Module_Gates::allows('appointments','write');
    }

    private static function requireStaff(bool $outcome): void
    {
        if (!self::ready(Access_Roles::currentRole(),$outcome)) {
            throw new Transaction_Failure('pc_appointment_denied');
        }
    }

    private static function scope(string $id,string $action): array
    {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'appointment',
            'resource_id'=>$id,
            'purpose'=>$action,
        ];
    }
}
