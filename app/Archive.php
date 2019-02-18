<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class Archive extends Model
{
    protected $table = 'archive';

    protected $casts = [
//        'dateTime' => 'custom_datetime',
        'inTemp' => 'double|temperature:fahrenheit,celsius',
        'outTemp' => 'double|temperature:fahrenheit,celsius',
        'heatindex' => 'double|temperature:fahrenheit,celsius',
        'inHumidity' => 'double|percentage',  // luftfeuchtigkeit innen
        'outHumidity' => 'double|percentage', // luftfeuchtigkeit aussen
        'barometer' => 'double|pressure:inhg,hpa',
        'altimeter' => 'double|pressure:inhg,hpa', // hoehenmesser ?
        'pressure' => 'double|pressure:inhg,hpa', // druck ?
        'dewpoint' => 'double|temperature:fahrenheit,celsius', // taupunkt
        'windchill' => 'double|temperature:fahrenheit,celsius', // gefuehlte temperatur
        'windDir' => 'integer', // windrichtung
        'windGust' => 'double|speed:mph,ms', // boeen
        'windSpeed' => 'double|speed:mph,ms', // windgeschwindigkeit
        'rainRate' => 'double|length:inch,mm', // menge regen pro stunde
        'rain' => 'double|length:inch,mm', // menge regen absolute
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
        return $query->select(['dateTime','windSpeed', 'windDir']);
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
                return new BaseCollection($this->fromJson($value));
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

    protected function GetLengthCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'inch');
        $targetUnit = array_get($rule, '1', 'mm');
        if ($srcUnit == 'inch' && $targetUnit == 'mm') {
            return doubleval($value) * 25.4;
        }
        throw new \Exception('conversion currently not implemented');
    }

    protected function GetSpeedCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'mph');
        $targetUnit = array_get($rule, '1', 'ms');
        if ($srcUnit == 'mph' && $targetUnit == 'ms') {
            return doubleval($value) * 0.44704;
        } elseif ($srcUnit == 'mph' && $targetUnit == 'kmh') {
            return doubleval($value) * 1.60934;
        }

        throw new \Exception('conversion currently not implemented');
    }

    protected function GetTemperatureCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'fahrenheit');
        $targetUnit = array_get($rule, '1', 'celsius');

        if ($srcUnit == 'fahrenheit' && $targetUnit == 'celsius') {
            return (doubleval($value) - 32) / 1.8;
        }

        throw new \Exception('conversion currently not implemented');
    }

    protected function GetPressureCast($value, $rule)
    {
        $srcUnit = array_get($rule, '0', 'inhg');
        $targetUnit = array_get($rule, '1', 'hpa');

        if ($srcUnit == 'inhg' && $targetUnit == 'hpa') {
            return doubleval($value) * 33.863886666667;
        }

        throw new \Exception('conversion currently not implemented');
    }

    protected function GetPercentageCast($value, $rule = [])
    {
        return doubleval($value)/100;
    }
}
