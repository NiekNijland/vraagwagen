<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class() extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::table('query_runs', function (Blueprint $collection): void {
            $collection->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('query_runs', function (Blueprint $collection): void {
            // Pass the column (not the resolved index name): the MongoDB blueprint derives
            // `correlation_id_1` from it, matching what `index('correlation_id')` created.
            $collection->dropIndex(['correlation_id']);
        });
    }
};
