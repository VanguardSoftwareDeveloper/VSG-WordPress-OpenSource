<?php
/**
 * IMP-53: R8-49, diagnóstico de solo lectura. Ningún valor marca P-C
 * como realizado ni contiene secretos, logs, SQL, rutas o credenciales.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Diagnostics_Service
{
    public static function read(): array
    {
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_settings_manage')
            || (int)\get_current_blog_id()<1) {
            throw new Rest_Failure('pc_access_denied',403);
        }
        $env = new Environment();
        $https = $env->has('https');
        $schema = $env->has('schema');
        $engine = $schema ? 'InnoDB' : 'unverified';
        $lockEvidence = $schema && Module_Gates::allows('documents','write');
        $storage = !\defined('PERUCOMMERCE_PRIVATE_DIR')
            ? 'not_configured'
            : (($env->has('private_storage')
                && Module_Gates::allows('digital','read'))
                ? 'verified' : 'pending_verification');
        $jobs = Transaction::run(static function (Transaction_Context $tx): int {
            $rows = $tx->rows(
                'SELECT (SELECT COUNT(*) FROM %i WHERE technical_phase IN (%s,%s,%s)) '
                . '+ (SELECT COUNT(*) FROM %i WHERE technical_phase IN (%s,%s,%s)) AS n',
                [Queue_Outbox::name('pc_outbox'),'pending','retry','processing',
                 Queue_Inbox::table(),'pending','retry','processing']
            );
            if (1 !== \count($rows) || !\preg_match('/\A[0-9]+\z/D',(string)($rows[0]['n'] ?? ''))) {
                throw new Transaction_Failure('pc_diagnostics_unavailable');
            }
            return (int)$rows[0]['n'];
        });
        return [
            'https_available'=>$https,
            'db_engine'=>$engine,
            'db_transactions_supported'=>$lockEvidence,
            'private_storage_status'=>$storage,
            // Cron programado no demuestra entrega efectiva ni recepción SMTP.
            'email_transport_status'=>'not_verified',
            'pending_jobs'=>$jobs,
        ];
    }
}
