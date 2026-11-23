<?php
/**
 * IMP-45 — R8-38 (A/O) y R8-54 (solo A) sobre la cita efectiva.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Appointment_Operations_Controller
{
    private static bool $registered=false;

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadAppointmentOperations();
        Rest_Router::bind(
            'R8-38',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Appointment_Operations::reschedule(
                    $ctx['path']['id'],$ctx['body']
                ),
            [Appointment_Operations::class,'guardReschedule']
        );
        Rest_Router::bind(
            'R8-54',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Appointment_Operations::outcome(
                    $ctx['path']['id'],$ctx['body']
                ),
            [Appointment_Operations::class,'guardOutcome']
        );
    }
}
