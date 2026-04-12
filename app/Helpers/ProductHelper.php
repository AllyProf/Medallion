<?php

namespace App\Helpers;

class ProductHelper
{
    /**
     * Generate a clean display name for products and food items.
     * 
     * @param string $productName The base name of the product (e.g., "Bonite (Coca-Cola)")
     * @param string|null $variantName The specific variant/size (e.g., "350ml - Crate")
     * @return string
     */
    public static function generateDisplayName($productName, $variantDescription = '', $variantSpecificName = '')
    {
        $vSpec = trim($variantSpecificName);
        $mAndPkg = trim($variantDescription);
        
        // Extract measurement (e.g. "750ml" from "750ml - Piece")
        $m = '';
        if (preg_match('/^(\d+(\.\d+)?\s*(ml|l|g|kg|btl|pcs)?)/i', $mAndPkg, $matches)) {
            $m = $matches[1];
        }
        $cleanM = $m;
        if (is_numeric($m) && $m > 0) {
            $cleanM = ($m < 10) ? $m.'L' : $m.'ml';
        }

        // 1. Determine if the variant name is a true identity (e.g., "Fanta Orange")
        $strippedV = preg_replace('/\b\d+(\.\d+)?\s*(ml|l|g|kg|pcs|btl)?\b/i', '', $vSpec);
        $strippedV = preg_replace('/\b(piece|pieces|pcs|crate|carton|box|bottle|btl|unit|pkg|package|ctn)\b/i', '', $strippedV);
        $strippedV = trim(preg_replace('/[-\s]+/', ' ', $strippedV), ' -');
        
        $genericDescriptors = ['dry', 'sweet', 'red', 'white', 'rose', 'light', 'dark', 'extra', 'premium', 'classic', 'original', 'regular', 'special', 'medium', 'semi', 'brut', 'demi', 'sec'];
        $strippedWords = str_word_count($strippedV);
        $isGenericSingle = ($strippedWords === 1 && in_array(strtolower($strippedV), $genericDescriptors));
        
        // Is usable if it's 2+ words, or a long single word that isn't a generic descriptor
        $useVariantName = !empty($strippedV) && ($strippedWords >= 2 || (strlen($strippedV) > 5 && !$isGenericSingle));

        if ($useVariantName) {
            // "Fanta Orange"
            $productNameBase = $vSpec;
        } else {
            // "Dodoma Red (Dry)" OR "Soft Drinks (Bonite)"
            $productNameBase = $productName;
            if (preg_match('/^(.+?)\s*\((.+?)\)(.*)$/u', $productNameBase, $nm)) {
                $beforeBracket = trim($nm[1]);
                $inBracket     = trim($nm[2]);
                $afterBracket  = trim($nm[3]);
                $genericCatWords = ['drink', 'beverage', 'beer', 'wine', 'spirit', 'soda', 'water', 'juice', 'alcohol', 'liquor', 'soft'];
                foreach ($genericCatWords as $gw) {
                    if (stripos($beforeBracket, $gw) !== false) {
                        // Extract "Bonite"
                        $productNameBase = $inBracket . ($afterBracket ? ' '.$afterBracket : '');
                        break;
                    }
                }
            }
        }

        // Append measurement if not already there (ignoring spaces for comparison)
        $nmBase = str_replace(' ', '', strtolower($productNameBase));
        $nmClean = str_replace(' ', '', strtolower($cleanM));
        
        if ($cleanM && stripos($nmBase, $nmClean) === false) {
            $productNameBase .= ' (' . $cleanM . ')';
        }
        
        // If the original product name was specific context we discarded, append it as a variant?
        // E.g. "Dodoma Red" vs "Dodoma Red (Dry)". If user specified $isGenericSingle = true, 
        // they might want "Dodoma Red (Dry)" instead of discarding "Dry".
        // Luckily, our logic keeps "Dodoma Red (Dry)" intact because it falls into the `else` block!

        return trim($productNameBase);
    }
}
