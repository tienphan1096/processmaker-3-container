<?php

namespace Database\Factories;

use App\Factories\Factory;
use G;
use Illuminate\Support\Str;
use ProcessMaker\Model\OutputDocument;
use ProcessMaker\Model\Process;

class OutputDocumentFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $date = $this->faker->dateTime();
        return [
            'OUT_DOC_UID' => G::generateUniqueID(),
            'OUT_DOC_ID' => $this->faker->unique()->numberBetween(1, 10000),
            'OUT_DOC_TITLE' => $this->faker->sentence(2),
            'OUT_DOC_DESCRIPTION' => $this->faker->sentence(10),
            'OUT_DOC_FILENAME' => $this->faker->sentence(2),
            'OUT_DOC_TEMPLATE' => '',
            'PRO_UID' => function () {
                $process = Process::factory()->create();
                return $process->PRO_UID;
            },
            'OUT_DOC_REPORT_GENERATOR' => 'TCPDF',
            'OUT_DOC_LANDSCAPE' => 0,
            'OUT_DOC_MEDIA' => 'Letter',
            'OUT_DOC_LEFT_MARGIN' => 20,
            'OUT_DOC_RIGHT_MARGIN' => 20,
            'OUT_DOC_TOP_MARGIN' => 20,
            'OUT_DOC_BOTTOM_MARGIN' => 20,
            'OUT_DOC_GENERATE' => 'BOTH',
            'OUT_DOC_TYPE' => 'HTML',
            'OUT_DOC_CURRENT_REVISION' => 0,
            'OUT_DOC_FIELD_MAPPING' => '',
            'OUT_DOC_VERSIONING' => 1,
            'OUT_DOC_DESTINATION_PATH' => '',
            'OUT_DOC_TAGS' => '',
            'OUT_DOC_PDF_SECURITY_ENABLED' => 0,
            'OUT_DOC_PDF_SECURITY_OPEN_PASSWORD' => '',
            'OUT_DOC_PDF_SECURITY_OWNER_PASSWORD' => '',
            'OUT_DOC_PDF_SECURITY_PERMISSIONS' => '',
            'OUT_DOC_OPEN_TYPE' => 1,
            'OUT_DOC_HEADER' => null,
            'OUT_DOC_FOOTER' => null
        ];
    }

}
