<?php

namespace Natsu007\Ntak\Models;

use Carbon\Carbon;
use Natsu007\Ntak\Enums\NTAKAmount;
use Natsu007\Ntak\Enums\NTAKCategory;
use Natsu007\Ntak\Enums\NTAKSubcategory;
use Natsu007\Ntak\Enums\NTAKVat;

class NTAKOrderItem
{
    public $name;
    public $category;
    public $subcategory;
    public $vat;
    public $price;
    public $amountType;
    public $amount;
    public $quantity;
    public $when;

    /**
     * __construct
     *
     * @param string $name
     * @param NTAKCategory    $category
     * @param NTAKSubcategory $subcategory
     * @param NTAKVat         $vat
     * @param float           $price
     * @param NTAKAmount      $amountType
     * @param float           $amount
     * @param int             $quantity
     * @param Carbon          $when
     *
     * @return void
     */
    public function __construct(
        string          $name,
        NTAKCategory    $category,
        NTAKSubcategory $subcategory,
        NTAKVat         $vat,
        float           $price,
        NTAKAmount      $amountType,
        float           $amount,
        float           $quantity,
        Carbon          $when
    ) {
        $this->name         = $name;
        $this->category     = $category;
        $this->subcategory  = $subcategory;
        $this->vat          = $vat;
        $this->price        = $price;
        $this->amountType   = $amountType;
        $this->amount       = $amount;
        $this->quantity     = $quantity;
        $this->when         = $when;
    }

    /**
     * buildRequest
     *
     * @param  bool $isAtTheSpot
     * @return array
     */
    public function buildRequest(bool $isAtTheSpot = true): array
    {
        $this->vat = ! $isAtTheSpot && $this->category == NTAKCategory::ALKMENTESITAL_HELYBEN()
            ?  NTAKVat::C_27()
            : $this->vat;

        return [
            'megnevezes'        => $this->name,
            'fokategoria'       => $this->category->getKey(),
            'alkategoria'       => $this->subcategory->getKey(),
            'afaKategoria'      => $this->vat->getKey(),
            'bruttoEgysegar'    => $this->price,
            'mennyisegiEgyseg'  => $this->amountType->getKey(),
            'mennyiseg'         => $this->amount,
            'tetelszam'         => $this->quantity,
            'rendelesIdopontja' => $this->when->timezone('Europe/Budapest')->toIso8601String(),
            'tetelOsszesito'    => round($this->quantity * $this->price),
        ];
    }

    /**
     * buildDiscountRequest
     *
     * @param  NTAKVat $vat
     * @param  int     $price
     * @param  Carbon  $when
     * @return array
     */
    public static function buildDiscountRequest(NTAKVat $vat, float $price, Carbon $when): array
    {
        return (
            new static(
                'Kedvezmény',
                NTAKCategory::EGYEB(),
                NTAKSubcategory::KEDVEZMENY(),
                $vat,
                $price,
                NTAKAmount::DARAB(),
                1,
                1,
                $when
            )
        )->buildRequest();
    }

    /**
     * buildServiceFeeRequest
     *
     * @param  NTAKVat $vat
     * @param  int     $price
     * @param  Carbon  $when
     * @return array
     */
    public static function buildServiceFeeRequest(NTAKVat $vat, float $price, Carbon $when): array
    {
        return (
            new static(
                'Szervízdíj',
                NTAKCategory::EGYEB(),
                NTAKSubcategory::SZERVIZDIJ(),
                $vat,
                $price,
                NTAKAmount::DARAB(),
                1,
                1,
                $when
            )
        )->buildRequest();
    }
}
