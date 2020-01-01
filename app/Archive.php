<?php

namespace App;

use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Archive extends Model
{
    protected $table = 'archive';

    protected $primaryKey = "dateTime";

    protected $casts = [
        'dateTime' => 'weewxdate',
        'inTemp' => 'float|temperature:fahrenheit,celsius|round:3',
        'outTemp' => 'float|temperature:fahrenheit,celsius|round:3',
        'heatindex' => 'float|temperature:fahrenheit,celsius|round:3',
        'inHumidity' => 'float|round:3',  // luftfeuchtigkeit innen
        'outHumidity' => 'float|round:3', // luftfeuchtigkeit aussen
        'barometer' => 'float|pressure:inhg,hpa|round:3',
        'altimeter' => 'float|pressure:inhg,hpa|round:3', // hoehenmesser ?
        'pressure' => 'float|pressure:inhg,hpa|round:3', // druck ?
        'dewpoint' => 'float|temperature:fahrenheit,celsius|round:3', // taupunkt
        'windchill' => 'float|temperature:fahrenheit,celsius|round:3', // gefuehlte temperatur
        'windDir' => 'integer', // windrichtung
        'windGustDir' => 'integer', // windrichtung boeenen
        'windGust' => 'float|speed:mph,ms|round:3', // boeenen
        'windSpeed' => 'float|speed:mph,ms|round:3', // windgeschwindigkeit
        'rainRate' => 'float|length:inch,mm|round:3', // menge regen pro stunde
        'rain' => 'float|length:inch,mm|round:3', // menge regen absolute
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('dateTime', 'desc');
        });
    }
    
    public function scopeWind($query)
    {
        return $query->select(['dateTime','windSpeed', 'windDir', 'windGust', 'windGustDir']);
    }

    public function scopeTemperature($query)
    {
        return $query->select(['dateTime','windchill', 'outTemp']);
    }

    public function scopeHumidity($query)
    {
        return $query->select(['dateTime','outHumidity', 'inHumidity']);
    }

    public function scopeBarometer($query)
    {
        return $query->select(['dateTime','barometer', 'pressure', 'altimeter']);
    }
    
    public function getEpochAttribute() {
        return Carbon::parse($this->dateTime)->addMinutes(2)->timestamp;
    }

    public function getUtcDateAttribute() {
        $date =  Carbon::createFromFormat('m/d/Y h:i:s A',$this->dateTime);
        return $date;
    }

    public function getRainPast24h()
    {
        $rain = self::where('dateTime', '>=', \Carbon\Carbon::now()->subDay()->timestamp)
            ->sum('rain');

        return $this->GetLengthCast($rain, $this->cast['rain']);
    }

    public function getRainCurrentMonth()
    {
        $rain = self::where('dateTime', '>=', \Carbon\Carbon::parse('first day of this month')->timestamp)
            ->sum('rain');

        return $this->GetLengthCast($rain, $this->cast['rain']);
    }

    public function getRainLastMonth()
    {
        $firstDay = \Carbon\Carbon::parse('first day of last month')->timestamp;
        $lastDay = \Carbon\Carbon::parse('last day of last month')->timestamp;

        $rain = self::where('dateTime', '>=', $firstDay)
            ->where('dateTime', '<=', $lastDay)
            ->sum('rain');

        return $this->GetLengthCast($rain, $this->cast['rain']);
    }

    protected function castAttribute($key, $value)
    {
        $value = parent::castAttribute($key, $value);
        
        if (is_null($value)) {
            return null;
        }

        $castTypes = collect(explode('|', $this->getCastType($key)));

        $castTypes->each(function ($type) use (&$value) {
            $rule = \Illuminate\Validation\ValidationRuleParser::parse($type);
            $key = array_get($rule, '0');

            if (method_exists($this, Str::studly('get' . $key . 'Cast'))) {
                $value = $this->{Str::studly('get' . $key . 'Cast') }($value, array_get($rule, '1', []));
            } else {
                $value = $this->defaultCast($key, $value);
            }
        });

        return $value;
    }

    private function defaultCast($key, $value)
    {
        switch ($key) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new Collection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
            default:
                return $value;
        }
    }

    protected function GetWeewxDateCast($value,$rule) {
        
        // dd(date('m/d/Y h:i:s A', 1575912660));
        $date = Carbon::createFromTimestamp($value,'UTC');
        return $date->format('m/d/Y h:i:s A');
    }

    protected function GetLengthCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'inch');
        $targetUnit = array_get($rule, '1', 'mm');
        if ($srcUnit == 'inch' && $targetUnit == 'mm') {
            return floatval($value) * 25.4;
        }
        throw new \Exception('conversion currently not implemented');
    }

    protected function GetRoundCast($value, $rule) {
        return round($value, $rule[0] ?? 2);
    }

    protected function GetSpeedCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'mph');
        $targetUnit = array_get($rule, '1', 'ms');
        if ($srcUnit == 'mph' && $targetUnit == 'ms') {
            return floatval($value) * 0.44704;
        } elseif ($srcUnit == 'mph' && $targetUnit == 'kmh') {
            return floatval($value) * 1.60934;
        }

        throw new \Exception('conversion currently not implemented');
    }

    protected function GetTemperatureCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'fahrenheit');
        $targetUnit = array_get($rule, '1', 'celsius');

        if ($srcUnit == 'fahrenheit' && $targetUnit == 'celsius') {
            return (floatval($value) - 32) / 1.8;
        }

        throw new \Exception('conversion currently not implemented');
    }

    protected function GetPressureCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'inhg');
        $targetUnit = array_get($rule, '1', 'hpa');

        if ($srcUnit == 'inhg' && $targetUnit == 'hpa') {
            return floatval($value) * 33.863886666667;
        }

        throw new \Exception('conversion currently not implemented');
    }

    protected function GetPercentageCast($value, $rule = [])
    {
        return floatval($value)/100;
    }

    protected function GetDecimalCast($value, $rule) {
        return number_format($value, $rule[0]);
    }

    protected function getAlmanacAttribute() {
        /* calculate the sunrise time for Lisbon, Portugal
            Latitude: 38.4 North
            Longitude: 9 West
            Zenith ~= 90
            offset: +1 GMT
        */
        
        $lat = floatval($this->station->latitude);
        $lng = floatval($this->station->longitude);
        // dd($lat, $lng, $this->station);
        /** @var Carbon $time*/
        $time = $this->UtcDate;

        $sun_info = collect(date_sun_info($time->timestamp, $lat, $lng))->map(function($v) {
            return Carbon::createFromTimestampUTC($v);
        });
        

        $moon = new \Solaris\MoonPhase($time->timestamp);
        $moon_fullness = 0;
        if ($moon->phase() <= 0.5) {
            $moon_fullness = abs($moon->phase() * 200);
        } else {
            $moon_fullness = abs(($moon->phase() - 0.5) * 200);
        }
        return json_decode(collect([
            "sunrise_minute" => $sun_info->get('sunrise')->minute,
            "sunrise_epoch"  => $sun_info->get('sunrise')->timestamp,
            "sunset" => $sun_info->get('sunset')->format("h:i:s A"), //03:54:58 PM",
            "sunrise_hour" => $sun_info->get('sunrise')->hour,
            "sunset_minute" => $sun_info->get('sunset')->minute,
            "sunset_epoch" => $sun_info->get('sunset')->timestamp,
            "moon" => collect([
                "moon_phase" => $moon->phase_name(),
                "moon_fullness" => round($moon_fullness,0),
                "moon_phase_raw" => round($moon->phase(),3),
            ]),
            "sunrise" => $sun_info->get('sunrise')->format("h:i:s A"),
            "sunset_hour" => $sun_info->get('sunset')->hour,
        ])->toJson());

// echo date("D M d Y"). ', sunrise time : ' .date_sunrise(time(), SUNFUNCS_RET_STRING, 38.4, -9, 90, 1);
    }

    protected function getStationAttribute() {
        return json_decode('{
            "latitude_dd": "55.8158264713",
            "altitude": "2 meters",
            "hardware": "FineOffsetUSB",
            "latitude": "48.95\' N",
            "longitude_dd": "8.21626371515",
            "archive_interval": "60",
            "longitude": "12.98\' E",
            "archive_interval_ms": "60000",
            "location": "Nymindegab, Denmark"
        }');
    }
}
