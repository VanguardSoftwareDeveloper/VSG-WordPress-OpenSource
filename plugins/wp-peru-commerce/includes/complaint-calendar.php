<?php
/**
 * IMP-49 — cálculo reproducible del vencimiento J-01, con feriados y
 * jornada de cierre RATIFICADOS por instalación. Sin heurística de feriados,
 * 15 días corridos, WP-Cron o fecha de servidor local implícita.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Calendar
{
    private static $calendarProvider=null;
    private const DAYS=15;
    private const MAX_WINDOW=120;

    /**
     * P-C: proveedor local, puro, sin HTTP/SQL/IO dentro de Transaction::run:
     * (string $firstDate, string $lastDate, array $merchant) ->
     * ['version' => '...', 'holidays' => ['YYYY-MM-DD', ...],
     *  'cutoff_local' => 'HH:MM:SS', 'zone' => 'America/Lima'].
     *
     * Quien registra el proveedor debe validar fuentes oficiales y contexto
     * jurídico del establecimiento; registrar un callable no lo certifica.
     */
    public static function registerVerifiedCalendarProvider(callable $provider):void
    {
        if(null!==self::$calendarProvider){
            throw new \LogicException('pc_complaint_calendar_duplicate');
        }
        self::$calendarProvider=$provider;
    }

    public static function ready():bool
    {
        return null!==self::$calendarProvider;
    }

    /**
     * @return array{due_at:string,calendar_version:string}
     * due_at: SQL UTC; la misma fecha alimenta R8-15 y R8-42.
     */
    public static function dueFor(string $submittedUtc,array $merchant):array
    {
        if(null===self::$calendarProvider){
            throw new Transaction_Failure('pc_complaint_calendar_unverified');
        }
        $submitted=Business_Time::fromDatabase($submittedUtc);
        $tz=Business_Time::businessZone('America/Lima');
        $start=$submitted->setTimezone($tz)->setTime(0,0,0,0)
            ->modify('+1 day');
        $end=$start->modify('+'.self::MAX_WINDOW.' days');
        try{
            $s=(self::$calendarProvider)(
                $start->format('Y-m-d'),$end->format('Y-m-d'),$merchant
            );
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_calendar_unverified');
        }
        if(!\is_array($s) || \count($s)!==4
            || []!==\array_diff(\array_keys($s),[
                'version','holidays','cutoff_local','zone'
            ]) || !\is_string($s['version'])
            || !\preg_match('/\A[a-zA-Z0-9_.-]{6,80}\z/D',$s['version'])
            || 'America/Lima'!==$s['zone']
            || !\is_string($s['cutoff_local'])
            || !\preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\z/D',
                $s['cutoff_local'])
            || !\is_array($s['holidays'])
            || !\array_is_list($s['holidays'])
            || \count($s['holidays'])>self::MAX_WINDOW){
            throw new Transaction_Failure('pc_complaint_calendar_unverified');
        }
        $holiday=[];
        foreach($s['holidays'] as $date){
            if(!\is_string($date)){
                throw new Transaction_Failure('pc_complaint_calendar_unverified');
            }
            try{$day=Business_Time::localDate($date);}
            catch(\Throwable $ignored){
                throw new Transaction_Failure('pc_complaint_calendar_unverified');
            }
            if($day<$start->format('Y-m-d')
                || $day>$end->format('Y-m-d')
                || isset($holiday[$day])){
                throw new Transaction_Failure('pc_complaint_calendar_unverified');
            }
            $holiday[$day]=true;
        }
        $cursor=$start;$count=0;
        for($i=0;$i<self::MAX_WINDOW;$i++){
            $day=$cursor->format('Y-m-d');
            if((int)$cursor->format('N')<=5 && !isset($holiday[$day])){
                ++$count;
                if(self::DAYS===$count){
                    [$hour,$minute,$second]=\array_map('intval',
                        \explode(':',$s['cutoff_local']));
                    $due=$cursor->setTime($hour,$minute,$second,0);
                    if($due<=$submitted){
                        throw new Transaction_Failure('pc_complaint_calendar_unverified');
                    }
                    return [
                        'due_at'=>Business_Time::toDatabase(
                            $due->setTimezone(new \DateTimeZone('UTC'))
                        ),
                        'calendar_version'=>$s['version'],
                    ];
                }
            }
            $cursor=$cursor->modify('+1 day');
        }
        throw new Transaction_Failure('pc_complaint_calendar_unverified');
    }
}
