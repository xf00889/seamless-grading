<?php

namespace Database\Factories;

use App\Enums\TemplateDocumentType;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        $documentType = fake()->randomElement(TemplateDocumentType::cases());
        $code = fake()->unique()->slug(2);
        $extension = $documentType === TemplateDocumentType::GradingSheet ? 'xlsx' : 'docx';

        return [
            'code' => $code,
            'name' => ucwords(str_replace('-', ' ', $code)),
            'description' => fake()->sentence(),
            'document_type' => $documentType,
            'version' => 1,
            'file_path' => sprintf('templates/%s/%s-v1.%s', $documentType->value, $code, $extension),
            'is_active' => false,
        ];
    }
}
