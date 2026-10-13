<?php

declare(strict_types=1);

use Slendie\Database\Migrations\Migration;
use Slendie\Database\Schema\Blueprint;
use Slendie\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('acme_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acme_posts');
    }
};
