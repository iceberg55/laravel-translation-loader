<?php

namespace Spatie\TranslationLoader;

use Dcat\Admin\Traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class LanguageLine extends Model
{

    use HasDateTimeFormatter;
    /** @var array */
//    public $translatable = ['text'];

    /** @var array */
    public $guarded = ['id'];

    /** @var array */
    //protected $casts = ['text' => 'array'];

    public static function boot()
    {
        parent::boot();

        $flushGroupCache = function (self $languageLine) {
            $languageLine->flushGroupCache();
        };

        static::saved($flushGroupCache);
        static::deleted($flushGroupCache);
    }

    public static function getTranslationsForGroup(string $locale, string $group): array
    {
        return Cache::rememberForever(static::getCacheKey($group, $locale), function () use ($group, $locale) {
            return static::query()
                    ->where('group', $group)
                    ->get()
                    ->reduce(function ($lines, self $languageLine) use ($group, $locale) {
                        $translation = $languageLine->getTranslation($locale);

                        if ($translation !== null && $group === '*') {
                            // Make a flat array when returning json translations
                            $lines[$languageLine->key] = $translation;
                        } elseif ($translation !== null && $group !== '*') {
                            // Make a nesetd array when returning normal translations
                            Arr::set($lines, $languageLine->key, $translation);
                        }

                        return $lines;
                    }) ?? [];
        });
    }

    public static function getCacheKey(string $group, string $locale): string
    {
        return "spatie.translation-loader.{$group}.{$locale}";
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    public function getTranslation(string $locale): ?string
    {
        if (! isset($this->$locale)) {
            $fallback = config('app.fallback_locale');

            return $this->$fallback ?? null;
        }

        return $this->$locale;
    }

    /**
     * @param string $locale
     * @param string $value
     *
     * @return $this
     */
    public function setTranslation(string $locale, string $value)
    {
        $this->$locale = array_merge($this->locale ?? [], [$locale => $value]);

        return $this;
    }

    public function flushGroupCache()
    {
        foreach (self::getSupportedLocales() as $locale) {
            Cache::forget(static::getCacheKey($this->group, $locale));
        }
    }

    public static function getSupportedLocales(): array
    {

        // if(!is_array($this->text)) {
        //     $this->text = json_decode($this->text);
        // }

        return config('translation-loader.supported_locales');
    }

    public static function groups() {
        return self::distinct()->pluck('group');
    }
}
