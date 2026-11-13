<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Libros fiscales que lleva Administración.
 */
enum FiscalBook: string
{
    case Sales = 'VENTAS';

    case Purchases = 'COMPRAS';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Libro de Ventas',
            self::Purchases => 'Libro de Compras',
        };
    }

    /**
     * «cliente» en ventas, «proveedor» en compras.
     */
    public function counterpartyLabel(): string
    {
        return match ($this) {
            self::Sales => 'Cliente',
            self::Purchases => 'Proveedor',
        };
    }

    /**
     * «Ventas» / «Compras», para los rótulos del resumen fiscal.
     */
    public function operationsLabel(): string
    {
        return match ($this) {
            self::Sales => 'Ventas',
            self::Purchases => 'Compras',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::Sales => 'ventas',
            self::Purchases => 'compras',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $book) {
            if ($book->slug() === $slug) {
                return $book;
            }
        }

        return null;
    }
}
