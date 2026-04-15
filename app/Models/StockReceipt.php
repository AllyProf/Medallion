<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_variant_id',
        'supplier_id',
        'receipt_number',
        'quantity_received',
        'total_units',
        'buying_price_per_unit',
        'selling_price_per_unit',
        'selling_price_per_tot',
        'total_buying_cost',
        'total_selling_value',
        'profit_per_unit',
        'total_profit',
        'discount_type',
        'discount_amount',
        'discount_value',
        'final_buying_cost',
        'received_date',
        'expiry_date',
        'notes',
        'received_by',
        'received_by_staff_id',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'total_units' => 'decimal:2',
        'buying_price_per_unit' => 'decimal:2',
        'selling_price_per_unit' => 'decimal:2',
        'selling_price_per_tot' => 'decimal:2',
        'total_buying_cost' => 'decimal:2',
        'total_selling_value' => 'decimal:2',
        'profit_per_unit' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'final_buying_cost' => 'decimal:2',
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * Generate a unique receipt number.
     */
    public static function generateReceiptNumber($userId)
    {
        $prefix = 'SR';
        $year = date('Y');
        $month = date('m');
        
        // Get last receipt number across all users to ensure global uniqueness
        $lastReceipt = self::where('receipt_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('receipt_number', 'desc')
            ->first();
        
        if ($lastReceipt) {
            $lastNumber = (int) substr($lastReceipt->receipt_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the owner (user) that owns this receipt.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the product variant for this receipt.
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Get the supplier for this receipt.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get a human-friendly display of the quantity (e.g. 10 Crate & 10 Btl).
     */
    public function getDisplayQuantityAttribute()
    {
        $conv = $this->productVariant->items_per_package ?? 0;
        if ($conv <= 1) {
            return number_format($this->total_units) . ' ' . ($this->productVariant->unit ?? 'Pcs');
        }

        $pkgs = floor($this->total_units / $conv);
        $loose = round($this->total_units % $conv);
        $pkgLabel = $this->productVariant->packaging ?? 'Pkg';
        $unitLabel = $this->productVariant->inventory_unit ?? 'Btl/Pc';
        $unitLabel = ucfirst(strtolower($unitLabel)); // Standardize casing (Btl, Pcs)

        if ($pkgs > 0 && $loose > 0) {
            return "{$pkgs} {$pkgLabel} & {$loose} {$unitLabel}";
        } elseif ($pkgs > 0) {
            return "{$pkgs} {$pkgLabel}";
        } else {
            return "{$loose} {$unitLabel}";
        }
    }

    /**
     * Get the user who received the stock (owner/super admin context).
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get the staff member who received the stock (when received by a staff account).
     */
    public function receivedByStaff()
    {
        return $this->belongsTo(\App\Models\Staff::class, 'received_by_staff_id');
    }

    /**
     * Get the display name of whoever received the stock.
     */
    public function getReceivedByNameAttribute(): string
    {
        if ($this->receivedByStaff) {
            return $this->receivedByStaff->full_name ?? $this->receivedByStaff->name ?? 'Staff';
        }
        return $this->receivedBy->name ?? 'System';
    }
}
