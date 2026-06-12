<?php

namespace App\Jobs;

use App\Models\Product;
use App\Support\ExtensionReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the AI security review for one product off the request cycle.
 */
class ReviewExtensionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public int $productId) {}

    public function handle(): void
    {
        $product = Product::find($this->productId);
        if ($product) {
            ExtensionReview::review($product);
        }
    }
}
