<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/bakame-php/dice-roller/
* @version 1.0.0
* @package bakame-php/dice-roller
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace Bakame\DiceRoller\Modifier;

use Bakame\DiceRoller\Cup;
use Bakame\DiceRoller\Exception;
use Bakame\DiceRoller\Rollable;

final class Explode implements Rollable
{
    const EQUALS = '=';
    const GREATER_THAN = '>';
    const LESSER_THAN = '<';

    /**
     * The Cup object to decorate
     *
     * @var Cup
     */
    private $rollable;

    /**
     * The threshold.
     *
     * @var int|null
     */
    private $threshold;

    /**
     * The comparison to use.
     *
     * @var string
     */
    private $compare;

    /**
     * @var string
     */
    private $trace;

    /**
     * new instance
     *
     * @param Cup      $rollable
     * @param string   $compare
     * @param int|null $threshold
     */
    public function __construct(Cup $rollable, string $compare, int $threshold = null)
    {
        $this->trace = '';
        $this->rollable = $rollable;
        $this->threshold = $threshold;

        if (!in_array($compare, [self::EQUALS, self::GREATER_THAN, self::LESSER_THAN], true)) {
            throw new Exception(sprintf('The submitted compared string `%s` is invalid or unsuported', $compare));
        }

        $this->compare = $compare;
        $this->validate();
    }

    /**
     * Validate the modifier state
     *
     * @throws Exception if the Modifier is in invalid state
     */
    private function validate()
    {
        $min = $this->rollable->getMinimum();
        $max = $this->rollable->getMaximum();
        $threshold = $this->threshold ?? $max;
        if (self::GREATER_THAN === $this->compare && $threshold <= $min) {
            throw new Exception(sprintf('This expression %s will generate a infinite loop', (string) $this));
        }

        if (self::LESSER_THAN === $this->compare && $threshold >= $max) {
            throw new Exception(sprintf('This expression %s will generate a infinite loop', (string) $this));
        }

        if (self::EQUALS === $this->compare && $threshold === $max && $min === $max) {
            throw new Exception(sprintf('This expression %s will generate a infinite loop', (string) $this));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $this->trace = '';
        $prefix = '!';
        if (self::EQUALS != $this->compare ||
            (self::EQUALS == $this->compare && null != $this->threshold)
        ) {
            $prefix .= $this->compare;
        }

        if (null !== $this->threshold) {
            $prefix .= $this->threshold;
        }

        $str = (string) $this->rollable;
        if (false !== strpos($str, '+')) {
            $str = '('.$str.')';
        }

        return $str.$prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getTrace(): string
    {
        return $this->trace;
    }

    /**
     * {@inheritdoc}
     */
    public function getMinimum(): int
    {
        $this->trace = '';
        return $this->rollable->getMinimum();
    }

    /**
     * {@inheritdoc}
     */
    public function getMaximum(): int
    {
        $this->trace = '';
        return PHP_INT_MAX;
    }

    /**
     * {@inheritdoc}
     */
    public function roll(): int
    {
        $sum = 0;
        $this->trace = '';
        foreach ($this->rollable as $innerRoll) {
            $sum = $this->calculate($sum, $innerRoll);
        }

        return $sum;
    }

    /**
     * Add the result of the Rollable::roll method to the submitted sum.
     *
     * @param int      $sum
     * @param Rollable $rollable
     *
     * @return int
     */
    private function calculate(int $sum, Rollable $rollable): int
    {
        $trace = [];
        $threshold = $this->threshold ?? $rollable->getMaximum();
        do {
            $res = $rollable->roll();
            $sum += $res;
            $str = $rollable->getTrace();
            if (false !== strpos($str, '+')) {
                $str = '('.$str.')';
            }
            $trace[] = $str;
        } while ($this->isValid($res, $threshold));

        $trace = implode(' + ', $trace);
        if ('' !== $this->trace) {
            $trace = ' + '.$trace;
        }

        $this->trace .= $trace;

        return $sum;
    }

    /**
     * Returns whether we should call the rollable again.
     *
     * @param int $result
     * @param int $threshold
     *
     * @return bool
     */
    private function isValid(int $result, int $threshold): bool
    {
        if (self::EQUALS == $this->compare) {
            return $result === $threshold;
        }

        if (self::GREATER_THAN === $this->compare) {
            return $result > $threshold;
        }

        return $result < $threshold;
    }
}