<?php

declare(strict_types=1);

use Slendie\Database\Migrations\Migration;
use Slendie\Database\Schema\Blueprint;
use Slendie\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
