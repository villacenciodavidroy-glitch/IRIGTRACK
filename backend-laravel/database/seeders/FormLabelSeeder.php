<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FormLabel;

class FormLabelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labels = [
            // Section: Basic Information
            [
                'key' => 'section_basic_info',
                'label' => '',
                'placeholder' => '',
                'section_title' => 'Basic Information',
                'section_subtitle' => 'Essential item details and identification',
                'helper_text' => null,
            ],
            [
                'key' => 'article',
                'label' => 'Article',
                'placeholder' => 'Enter article',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'category',
                'label' => 'Category',
                'placeholder' => 'Select category',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'description',
                'label' => 'Description',
                'placeholder' => 'Enter description',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'serial_number',
                'label' => 'Serial Number',
                'placeholder' => 'Auto-generated serial number',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => 'Auto-generated unique identifier for equipment tracking',
            ],
            [
                'key' => 'model',
                'label' => 'Model',
                'placeholder' => 'Enter model',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            // Section: Financial & Acquisition Details
            [
                'key' => 'section_financial',
                'label' => '',
                'placeholder' => '',
                'section_title' => 'Financial & Acquisition Details',
                'section_subtitle' => 'Property account code, valuation, and acquisition information',
                'helper_text' => null,
            ],
            [
                'key' => 'property_account_code',
                'label' => 'Property Account Code',
                'placeholder' => 'Enter property account code',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'unit_value',
                'label' => 'Unit Value',
                'placeholder' => '32,200.00',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'quantity',
                'label' => 'Quantity',
                'placeholder' => 'Enter quantity',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'date_acquired',
                'label' => 'Date Acquired',
                'placeholder' => 'mm/dd/yyyy',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'po_number',
                'label' => 'P.O Number',
                'placeholder' => 'Enter P.O number',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            // Section: Assignment & Unit/Sections
            [
                'key' => 'section_assignment',
                'label' => '',
                'placeholder' => '',
                'section_title' => 'Assignment & Unit/Sections',
                'section_subtitle' => 'Item unit/sections and personnel assignment',
                'helper_text' => null,
            ],
            [
                'key' => 'unit_sections',
                'label' => 'Unit/Sections',
                'placeholder' => 'Select Unit/Section',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'issued_to',
                'label' => 'Issued To',
                'placeholder' => 'Select Personnel',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            // Section: Condition & Status
            [
                'key' => 'section_condition',
                'label' => '',
                'placeholder' => '',
                'section_title' => 'Condition & Status',
                'section_subtitle' => 'Item condition assessment and classification',
                'helper_text' => null,
            ],
            [
                'key' => 'condition',
                'label' => 'Condition',
                'placeholder' => 'Select condition',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            [
                'key' => 'condition_number',
                'label' => 'Condition Number',
                'placeholder' => 'Select Condition Number',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => null,
            ],
            // Section: Asset Image
            [
                'key' => 'section_asset_image',
                'label' => '',
                'placeholder' => '',
                'section_title' => 'Asset Image',
                'section_subtitle' => 'Upload item photograph or documentation image',
                'helper_text' => null,
            ],
            [
                'key' => 'item_image',
                'label' => 'Item Image',
                'placeholder' => 'Click to upload or drag and drop',
                'section_title' => null,
                'section_subtitle' => null,
                'helper_text' => 'PNG, JPG, GIF up to 10MB',
            ],
        ];

        foreach ($labels as $label) {
            FormLabel::updateOrCreate(
                ['key' => $label['key']],
                $label
            );
        }
    }
}
