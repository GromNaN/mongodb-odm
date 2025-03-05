<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Types;

use DateTimeInterface;
use Symfony\Component\Clock\DatePoint;

class DatePointType extends DateType
{
    /** @return DatePoint */
    public static function getDateTime($value): DateTimeInterface
    {
        return DatePoint::createFromInterface(parent::getDateTime($value));
    }

    /**
     * @param mixed $current
     *
     * @return DateTimeInterface
     */
    public function getNextVersion($current)
    {
        return new DatePoint();
    }
}
