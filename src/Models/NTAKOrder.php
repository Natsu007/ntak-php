<?php

namespace Natsu007\Ntak\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use Natsu007\Ntak\Enums\NTAKOrderType;
use Natsu007\Ntak\Enums\NTAKPaymentType;
use Natsu007\Ntak\Enums\NTAKVat;

class NTAKOrder
{
    protected $total;
    protected $totalWithDiscount;
    public    $orderType;
    public    $orderId;
    public    $orderItems;
    public    $ntakOrderId;
    public    $start;
    public    $end;
    public    $isAtTheSpot;
    public    $payments;
    public    $discount;
    public    $serviceFee;

    /**
     * __construct
     *
     * @param  NTAKOrderType            $orderType
     * @param  string                   $orderId
     * @param  array|NTAKOrderItem[]    $orderItems
     * @param  string                   $ntakOrderId
     * @param  Carbon                   $start
     * @param  Carbon                   $end
     * @param  bool                     $isAtTheSpot
     * @param  array|null|NTAKPayment[] $payments
     * @param  int                      $discount
     * @param  int                      $serviceFee
     * @return void
     */
    public function __construct(
        NTAKOrderType    $orderType,
        string           $orderId,
        ?array           $orderItems = null,
        ?string          $ntakOrderId = null,
        ?Carbon          $start = null,
        ?Carbon          $end = null,
        bool             $isAtTheSpot = true,
        ?array           $payments = null,
        int              $discount = 0,
        int              $serviceFee = 0
    ) {
        $this->orderType   = $orderType;
        $this->orderId     = $orderId;
        $this->orderItems  = $orderItems;
        $this->ntakOrderId = $ntakOrderId;
        $this->start       = $start;
        $this->end         = $end;
        $this->isAtTheSpot = $isAtTheSpot;
        $this->payments    = $payments;
        $this->discount    = $discount;
        $this->serviceFee  = $serviceFee;

        if ($orderType == NTAKOrderType::NORMAL()) {
            $this->validateIfNormal();
        }
        if ($orderType != NTAKOrderType::NORMAL()) {
            $this->validateIfNotNormal();
        }
        if ($orderType != NTAKOrderType::SZTORNO()) {
            $this->validateIfNotStorno();
        }

        $this->total = $this->calculateTotal();
        $this->totalWithDiscount = $this->calculateTotalWithDiscount();
    }

    /**
     * buildOrderItems
     *
     * @return array
     */
    public function buildOrderItems(): ?array
    {
        $orderItems = $this->orderItems === null
            ? null
            : array_map(
                function(NTAKOrderItem $orderItem) {
                    return $orderItem->buildRequest($this->isAtTheSpot);
                },
                $this->orderItems
            );

        if ($orderItems !== null && $this->discount > 0) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        if ($orderItems !== null && $this->serviceFee > 0) {
            $orderItems = $this->buildServiceFeeRequests($orderItems);
        }

        return $orderItems;
    }

    /**
     * buildPaymentTypes
     *
     * @return array
     */
    public function buildPaymentTypes(): array
    {
        $payments = array_map(
            function(NTAKPayment $payment) {
                return $payment->buildRequest();
            }
            ,
            $this->payments
        );

        foreach ($this->payments as $payment) {
            if ($payment->round() !== 0) {
                $payments[] = [
                    'fizetesiMod'       => NTAKPaymentType::KEREKITES()->getKey(),
                    'fizetettOsszegHUF' => $payment->round(),
                ];

                break;
            }
        }

        return $payments;
    }

    /**
     * total getter
     *
     * @return int|null
     */
    public function total(): ?int
    {
        return $this->total;
    }

    /**
     * totalWithDiscount getter
     *
     * @return int|null
     */
    public function totalWithDiscount(): ?int
    {
        return $this->totalWithDiscount;
    }

    /**
     * validateIfNormal
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateIfNormal(): void
    {
        if ($this->discount > 100) {
            throw new InvalidArgumentException('discount cannot be greater than 100');
        }
    }

    /**
     * validateIfNotNormal
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateIfNotNormal(): void
    {
        if ($this->ntakOrderId === null) {
            throw new InvalidArgumentException('ntakOrderId cannot be null in this case');
        }
    }

    /**
     * validateIfNotStorno
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateIfNotStorno(): void
    {
        if ($this->orderItems === null || count($this->orderItems) === 0) {
            throw new InvalidArgumentException('orderItems cannot be null in this case');
        }

        foreach ($this->orderItems as $orderItem) {
            if (! $orderItem instanceof NTAKOrderItem) {
                throw new InvalidArgumentException('orderItems must be an array of NTAKOrderItem instances');
            }
        }

        if ($this->start === null) {
            throw new InvalidArgumentException('start cannot be null in this case');
        }

        if ($this->end === null) {
            throw new InvalidArgumentException('end cannot be null in this case');
        }

        if (count($this->payments) === 0 || $this->payments === null) {
            throw new InvalidArgumentException('paymentType cannot be null in this case');
        }
    }

    /**
     * calculateTotal
     *
     * @return int
     */
    protected function calculateTotal(): int
    {
        if ($this->orderType != NTAKOrderType::SZTORNO()) {
            $total = $this->totalOfOrderItems($this->orderItems);

            return round($total + $total * $this->serviceFee / 100);
        }

        return 0;
    }

    /**
     * calculateTotalWithDiscount
     *
     * @return int
     */
    protected function calculateTotalWithDiscount(): int
    {
        if ($this->discount === 0) {
            return $this->total;
        }

        if ($this->orderType != NTAKOrderType::SZTORNO()) {
            $total = $this->totalOfOrderItemsWithDiscount($this->orderItems);

            return round($total + $total * $this->serviceFee / 100);
        }

        return 0;
    }

    /**
     * buildDiscountRequests
     *
     * @param  array $orderItems
     * @return array
     */
    protected function buildDiscountRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();

        foreach ($vats as $vat) {
            $orderItems = $this->addDiscountRequestByVat($orderItems, $vat);
        }

        return $orderItems;
    }

    /**
     * buildServiceFeeRequests
     *
     * @param  array $orderItems
     * @return array
     */
    protected function buildServiceFeeRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();

        foreach ($vats as $vat) {
            $orderItems = $this->addServiceFeeRequestByVat($orderItems, $vat);
        }

        return $orderItems;
    }

    /**
     * addDiscountRequestByVat
     *
     * @param  array   $orderItems
     * @param  NTAKVat $vat
     * @return array
     */
    protected function addDiscountRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);

        $totalOfOrderItems = $this->totalOfOrderItems($orderItemsWithVat);
        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);

        $orderItems[] = NTAKOrderItem::buildDiscountRequest(
            $vat,
            $totalOfOrderItemsWithDiscount - $totalOfOrderItems,
            $this->end
        );

        return $orderItems;
    }

    /**
     * addServiceFeeRequestByVat
     *
     * @param  array   $orderItems
     * @param  NTAKVat $vat
     * @return array
     */
    protected function addServiceFeeRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);

        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);

        $orderItems[] = NTAKOrderItem::buildServiceFeeRequest(
            $vat,
            $totalOfOrderItemsWithDiscount * $this->serviceFee / 100,
            $this->end
        );

        return $orderItems;
    }

    /**
     * orderItemsWithVat
     *
     * @param  NTAKVat $vat
     * @return array
     */
    protected function orderItemsWithVat(NTAKVat $vat): array
    {
        return array_filter(
            $this->orderItems,
            function(NTAKOrderItem $orderItem) use($vat) {
                return $orderItem->vat == $vat;
            }
        );
    }

    /**
     * totalOfOrderItems
     *
     * @param  array $orderItems
     * @return int
     */
    protected function totalOfOrderItems(array $orderItems): int
    {
        return array_reduce(
            $orderItems,
            function (int $carry, NTAKOrderItem $orderItem) {
                return $carry + round($orderItem->price * $orderItem->quantity);
            },
            0
        );
    }

    /**
     * totalOfOrderItemsWithDiscount
     *
     * @param  array|NTAKOrderItem[] $orderItems
     * @return int
     */
    protected function totalOfOrderItemsWithDiscount(array $orderItems): int
    {
        return array_reduce(
            $orderItems,
            function (int $carry, NTAKOrderItem $orderItem) {
                $price = round($orderItem->price * $orderItem->quantity) *
                         (1 - $this->discount / 100);

                return $carry + $price;
            },
            0
        );
    }

    /**
     * uniqueVats
     *
     * @return array
     */
    protected function uniqueVats(): array
    {
        return array_unique(
            array_map(
                function(NTAKOrderItem $orderItem) {
                    return $orderItem->vat;
                },
                $this->orderItems
            ),
            SORT_REGULAR
        );
    }
}
