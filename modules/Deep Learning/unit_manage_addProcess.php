<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Services\Format;
use Gibbon\Data\Validator;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Module\DeepLearning\Domain\UnitTagGateway;
use Gibbon\Module\DeepLearning\Domain\UnitAuthorGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML', 'teachersNotes' => 'HTML', 'letterToParents' => 'HTML', 'riskAssessment' => 'HTML']);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/unit_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/unit_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $unitGateway = $container->get(UnitGateway::class);
    $unitTagGateway = $container->get(UnitTagGateway::class);
    $unitAuthorGateway = $container->get(UnitAuthorGateway::class);
    
    $data = [
        'name'                   => $_POST['name'] ?? '',
        'status'                 => $_POST['status'] ?? 'Draft',
        'cost'                   => !empty($_POST['cost']) ? $_POST['cost'] : null,
        'location'               => $_POST['location'] ?? '',
        'provider'               => $_POST['provider'] ?? '',
        'majors'                 => $_POST['majors'] ?? '',
        'minors'                 => $_POST['minors'] ?? '',
        'enrolmentMin'           => $_POST['enrolmentMin'] ?? null,
        'enrolmentMax'           => $_POST['enrolmentMax'] ?? null,
        'description'            => $_POST['description'] ?? '',
        'teachersNotes'          => $_POST['teachersNotes'] ?? '',
        'letterToParents'        => $_POST['letterToParents'] ?? '',
        'riskAssessment'         => $_POST['riskAssessment'] ?? '',
        'timestampCreated'       => date('Y-m-d H:i:s'),
        'timestampModified'      => date('Y-m-d H:i:s'),
        'gibbonPersonIDCreated'  => $_POST['gibbonPersonIDCreated'] ?? $session->get('gibbonPersonID'),
        'gibbonPersonIDModified' => $session->get('gibbonPersonID'),
    ];

    // Validate the required values are present
    if (empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$unitGateway->unique($data, ['name'])) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Move attached file, if there is one
    if (!empty($_FILES['headerImageFile']['tmp_name'])) {
        $fileUploader = new Gibbon\FileUploader($pdo, $session);
        $fileUploader->getFileExtensions('Graphics/Design');

        $file = $_FILES['headerImageFile'] ?? null;

        // Upload the file, return the /uploads relative path
        $data['headerImage'] = $fileUploader->uploadFromPost($file, $data['name']);

        if (empty($data['headerImage'])) {
            $partialFail = true;
        }
    }

    // Ensure tags are uppercase and trimmed
    if (!empty($data['majors'])) {
        $data['majors'] = implode(',', array_filter(array_map(function ($item) {
            return trim(ucwords($item));
        }, explode(',', $data['majors']))));
    }

    if (!empty($data['minors'])) {
        $data['minors'] = implode(',', array_filter(array_map(function ($item) {
            return trim(ucwords($item));
        }, explode(',', $data['minors']))));
    }

    // Create the record
    $deepLearningUnitID = $unitGateway->insert($data);

    if (empty($deepLearningUnitID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Create the author
    $inserted = $unitAuthorGateway->insert([
        'deepLearningUnitID' => $deepLearningUnitID,
        'gibbonPersonID' => $data['gibbonPersonIDCreated'],
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    $partialFail &= !$inserted;

    // Create the tags
    $tags = array_unique(array_filter(array_merge(explode(',', $data['majors'] ?? ''), explode(',', $data['minors'] ?? ''))));
    foreach ($tags as $tag) {
        $unitTagGateway->insertAndUpdate(['tag' => $tag], ['tag' => $tag]);
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0&editID=$deepLearningUnitID";

    header("Location: {$URL}");
}
