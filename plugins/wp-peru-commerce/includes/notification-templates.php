<?php
/**
 * IMP-47: plantillas es-PE v1, cerradas y en texto plano.
 * No hay editor HTML, tracking secreto, OTP, datos fiscales ni adjuntos.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Notification_Templates
{
    /** @return array{subject:string,body:string} */
    public static function render(string $code,array $context):array
    {
        $merchant=$context['merchant']??'El comercio';
        if(!\is_string($merchant) || ''===\trim($merchant)
            || \mb_strlen($merchant,'UTF-8')>160
            || \preg_match('/[\r\n\x00-\x1F\x7F]/u',$merchant)){
            $merchant='El comercio';
        }
        $prefix='PeruCommerce · '.$merchant;
        $entries=[
            'complaint_received'=>['Reclamación registrada',
                'Se registró una presentación en el Libro de Reclamaciones del comercio. La copia íntegra se entregó en el navegador del formulario original; este mensaje no sustituye el comprobante ni constituye una respuesta jurídica.'],
            'physical_preparing'=>['Preparación de su pedido',
                'Se registró el inicio de preparación de un artículo de su pedido.'],
            'physical_ready'=>['Artículo listo',
                'Se registró que un artículo de su pedido está listo para el siguiente paso.'],
            'physical_dispatched'=>['Despacho registrado',
                'Se registró el despacho de un artículo. Esto todavía NO acredita entrega al comprador.'],
            'physical_delivered'=>['Entrega registrada',
                'El comercio registró la entrega de un artículo de su pedido.'],
            'checkout_cash_confirmed'=>['Pedido confirmado en efectivo',
                'Su pedido se registró con modalidad de pago en efectivo. Esta confirmación no es un recibo de dinero cobrado.'],
            'mp_payment_confirmed'=>['Pago verificado',
                'Se registró la acreditación de un pago mediante Mercado Pago, tras verificación del proveedor.'],
            'cash_received'=>['Efectivo registrado',
                'El comercio registró la recepción de efectivo de su pedido. Esto no sustituye un comprobante tributario.'],
            'line_cancelled'=>['Cambio registrado en su pedido',
                'Se registró la cancelación de un artículo o servicio del pedido. Cualquier devolución requiere una verificación separada.'],
            'external_refund_verified'=>['Reintegro externo registrado',
                'El comercio registró un resultado de reintegro externo que ha verificado por su cuenta. No se efectuó desde PeruCommerce.'],
            'logistics_incident_opened'=>['Incidencia de entrega',
                'El comercio registró una incidencia en la entrega de su pedido. Se encuentra pendiente de evaluación y no supone cancelación ni reintegro.'],
            'service_cancelled'=>['Cita cancelada',
                'El comercio registró la cancelación de su cita. Esto no acredita que se haya realizado una devolución de dinero.'],
            'delivery_change_approved'=>['Cambio de dirección aprobado',
                'El comercio registró un cambio de destino aprobado. Consulte la información vigente en su seguimiento privado.'],
            'cash_corrected'=>['Corrección de caja registrada',
                'El comercio registró una corrección documentada de un cobro en efectivo; consulte su información vigente.'],
            'order_financial_adjusted'=>['Ajuste comercial registrado',
                'El comercio registró un ajuste comercial; esto no acredita un reintegro externo efectuado.'],
            'digital_access_enabled'=>['Acceso digital habilitado',
                'El comercio registró el acceso autorizado a sus productos digitales. Los archivos protegidos no se adjuntan por correo.'],
            'service_confirmed'=>['Servicio confirmado',
                'Se registró la confirmación de su cita presencial.'],
            'service_rescheduled'=>['Cita reprogramada',
                'El comercio registró un cambio del horario de su cita.'],
        ];
        if(!isset($entries[$code])){
            throw new Transaction_Failure('pc_notification_template_invalid');
        }
        [$subject,$message]=$entries[$code];
        if('service_rescheduled'===$code){
            foreach(['before','after'] as $field){
                if(!\is_string($context[$field]??null)
                    || !\preg_match('/\A[0-9]{2}\/[0-9]{2}\/[0-9]{4} [0-9]{2}:[0-9]{2}\z/D',
                        $context[$field])){
                    throw new Transaction_Failure('pc_notification_template_invalid');
                }
            }
            $message.="\nHorario anterior: ".$context['before']
                ."\nNuevo horario: ".$context['after'];
        }
        if('service_confirmed'===$code && \is_string($context['at']??null)){
            $message.="\nHorario de cita: ".$context['at'];
        }
        if('external_refund_verified'===$code && \is_string($context['amount']??null)){
            if(!\preg_match('/\A[0-9]+[.][0-9]{2}\z/D',$context['amount'])){
                throw new Transaction_Failure('pc_notification_template_invalid');
            }
            $message.="\nImporte registrado: S/ ".$context['amount'];
        }
        $message.="\n\nConsulte el estado vigente de su pedido por su canal de seguimiento privado o comuníquese directamente con el comercio.";
        $signature=$context['signature']??null;
        if(\is_string($signature) && ''!==\trim($signature)
            && \mb_strlen($signature,'UTF-8')<=500
            && !\preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$signature)){
            $message.="\n\n".$signature;
        }
        return ['subject'=>$prefix.' — '.$subject,'body'=>$message];
    }
}
