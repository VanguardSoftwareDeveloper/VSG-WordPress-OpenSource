<?php
/** IMP-50 — plazos ARCO J-04 con fuente local ratificada, no calendario improvisado. */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Calendar
{
    private static $provider=null;
    private const MAX_DAYS=120;
    private const PERIODS=[
        'access'=>20,'rectification'=>10,
        'cancellation'=>10,'opposition'=>10,
    ];
    /** 
     * P-C: callback puro que suministra versión, feriados, zona y hora de
     * corte de la instalación; revisa DS016-2024-JUS, suspensiones y
     * prórrogas por caso. No consultar HTTP ni WordPress dentro del TX.
     */
    public static function registerVerifiedProvider(callable $provider):void
    {
        if(null!==self::$provider){
            throw new \LogicException('pc_privacy_calendar_duplicate');
        }
        self::$provider=$provider;
    }
    public static function ready():bool{return null!==self::$provider;}

    /** @return array{due_at:string,calendar_version:string} */
    public static function dueFor(
        string $receivedUtc,string $type,array $source
    ):array {
        if(null===self::$provider || !isset(self::PERIODS[$type])){
            throw new Transaction_Failure('pc_privacy_calendar_unverified');
        }
        $received=Business_Time::fromDatabase($receivedUtc);
        $zone=Business_Time::businessZone('America/Lima');
        $start=$received->setTimezone($zone)->setTime(0,0)
            ->modify('+1 day');
        $end=$start->modify('+'.self::MAX_DAYS.' days');
        try{
            $calendar=(self::$provider)(
                $start->format('Y-m-d'),$end->format('Y-m-d'),
                $type,$source
            );
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_calendar_unverified');
        }
        if(!\is_array($calendar)
            || \count($calendar)!==4
            || []!==\array_diff(\array_keys($calendar),[
                'version','holidays','cutoff_local','zone'
            ])
            || !\is_string($calendar['version'])
            || !\preg_match('/\A[a-zA-Z0-9_.-]{6,80}\z/D',$calendar['version'])
            || 'America/Lima'!==$calendar['zone']
            || !\is_string($calendar['cutoff_local'])
            || !\preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\z/D',
                $calendar['cutoff_local'])
            || !\is_array($calendar['holidays'])
            || !\array_is_list($calendar['holidays'])
            || \count($calendar['holidays'])>self::MAX_DAYS){
            throw new Transaction_Failure('pc_privacy_calendar_unverified');
        }
        $holidays=[];
        foreach($calendar['holidays'] as $date){
            if(!\is_string($date)){
                throw new Transaction_Failure('pc_privacy_calendar_unverified');
            }
            try{$date=Business_Time::localDate($date);}
            catch(\Throwable $ignored){
                throw new Transaction_Failure('pc_privacy_calendar_unverified');
            }
            if($date<$start->format('Y-m-d')
                || $date>$end->format('Y-m-d')
                || isset($holidays[$date])){
                throw new Transaction_Failure('pc_privacy_calendar_unverified');
            }
            $holidays[$date]=true;
        }
        $cursor=$start;
        $days=0;
        for($i=0;$i<self::MAX_DAYS;$i++){
            if((int)$cursor->format('N')<=5
                && !isset($holidays[$cursor->format('Y-m-d')])){
                ++$days;
                if($days===self::PERIODS[$type]){
                    [$h,$m,$s]=\array_map('intval',
                        \explode(':',$calendar['cutoff_local']));
                    $due=$cursor->setTime($h,$m,$s);
                    if($due<=$received){
                        throw new Transaction_Failure('pc_privacy_calendar_unverified');
                    }
                    return ['due_at'=>Business_Time::toDatabase(
                        $due->setTimezone(new \DateTimeZone('UTC'))
                    ),'calendar_version'=>$calendar['version']];
                }
            }
            $cursor=$cursor->modify('+1 day');
        }
        throw new Transaction_Failure('pc_privacy_calendar_unverified');
    }
}
