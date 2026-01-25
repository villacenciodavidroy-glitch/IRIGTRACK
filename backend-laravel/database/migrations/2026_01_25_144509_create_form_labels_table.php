<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_labels', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Unique identifier for the label (e.g., article, category, description)');
            $table->string('label')->comment('Display text for the label');
            $table->string('placeholder')->nullable()->comment('Placeholder text for input fields');
            $table->string('section_title')->nullable()->comment('Section header title');
            $table->string('section_subtitle')->nullable()->comment('Section header subtitle/description');
            $table->string('helper_text')->nullable()->comment('Helper text below the field');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_labels');
    }
};
