<?php

namespace Widmogrod\Primitive;

use Widmogrod\Common;
use Widmogrod\FantasyLand;
use Widmogrod\Functional as f;

class Listt implements
    FantasyLand\Monad,
    FantasyLand\Monoid,
    FantasyLand\Setoid,
    FantasyLand\Foldable,
    FantasyLand\Traversable,
    Common\ValueOfInterface
{
    use Common\PointedTrait;

    const of = 'Widmogrod\Primitive\Listt::of';

    /**
     * @param array $value
     */
    public function __construct($value)
    {
        $this->value = f\isNativeTraversable($value)
            ? $value
            : [$value];
    }

    /**
     * @inheritdoc
     */
    public function map(callable $transformation)
    {
        $result = [];
        foreach ($this->value as $value) {
            $result[] = call_user_func($transformation, $value);
        }

        return self::of($result);
    }

    /**
     * @inheritdoc
     *
     * fs <*> xs = [f x | f <- fs, x <- xs]
     */
    public function ap(FantasyLand\Apply $applicative)
    {
        return $this->reduce(function ($accumulator, $value) use ($applicative) {
            /** @var $applicative self */
            return f\concatM($accumulator, $applicative->map($value));
        }, self::mempty());
    }

    /**
     * @inheritdoc
     */
    public function bind(callable $transformation)
    {
        // xs >>= f = concat (map f xs)
        return self::of(f\concat(f\map($transformation, $this)));
    }

    /**
     * @inheritdoc
     */
    public function extract()
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function reduce(callable $function, $accumulator)
    {
        foreach ($this->value as $item) {
            $accumulator = call_user_func($function, $accumulator, $item);
        }

        return $accumulator;
    }

    /**
     * @inheritdoc
     *
     * Example from haskell source code:
     * ``` haskell
     * traverse f = List.foldr cons_f (pure [])
     *  where cons_f x ys = (:) <$> f x <*> ys
     * ```
     */
    public function traverse(callable $transformation)
    {
        return f\foldr(function ($ys, $x) use ($transformation) {
            $functor =  $transformation($x);

            return $functor
                ->map(f\append)
                ->ap($ys ? $ys : $functor::of([])); // https://github.com/widmogrod/php-functional/issues/30
        }, false, $this);
    }

    /**
     * @inheritdoc
     */
    public static function mempty()
    {
        return self::of([]);
    }

    /**
     * @inheritdoc
     */
    public function getEmpty()
    {
        return self::mempty();
    }

    /**
     * @inheritdoc
     */
    public function concat(FantasyLand\Semigroup $value)
    {
        if ($value instanceof self) {
            return self::of($value->reduce(function ($accumulator, $item) {
                $accumulator[] = $item;

                return $accumulator;
            }, $this->extract()));
        }

        throw new TypeMismatchError($value, self::class);
    }

    /**
     * @inheritdoc
     */
    public function equals($other)
    {
        return $other instanceof self
            ? $this->extract() === $other->extract()
            : false;
    }
}
