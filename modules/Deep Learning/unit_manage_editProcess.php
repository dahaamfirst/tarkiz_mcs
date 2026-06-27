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
use Gibbon\FileUploader;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Module\DeepLearning\Domain\UnitTagGateway;
use Gibbon\Module\DeepLearning\Domain\UnitAuthorGateway;
use Gibbon\Module\DeepLearning\Domain\UnitPhotoGateway;
use Gibbon\Module\DeepLearning\Domain\UnitBlockGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML', 'teachersNotes' => 'HTML', 'letterToParents' => 'HTML', 'riskAssessment' => 'HTML', 'content*' => 'HTML']);

$deepLearningUnitID = $_POST['deepLearningUnitID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/unit_manage_edit.php&deepLearningUnitID='.$deepLearningUnitID;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/unit_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {

    // Proceed!
    $unitGateway = $container->get(UnitGateway::class);
    $unitTagGateway = $container->get(UnitTagGateway::class);
    $unitAuthorGateway = $container->get(UnitAuthorGateway::class);
    $unitPhotoGateway = $container->get(UnitPhotoGateway::class);
    $unitBlockGateway = $container->get(UnitBlockGateway::class);

    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    $canEditUnit = $unitGateway->getUnitEditAccess($deepLearningUnitID, $session->get('gibbonPersonID'));
    if ($highestAction != 'Manage Units_all' && $canEditUnit != 'Y') {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

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
        'timestampModified'      => date('Y-m-d H:i:s'),
        'gibbonPersonIDModified' => $session->get('gibbonPersonID'),
    ];

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

    // Validate the required values are present
    if (empty($deepLearningUnitID) || empty($data['name']) ) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$unitGateway->exists($deepLearningUnitID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$unitGateway->unique($data, ['name'], $deepLearningUnitID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    $fileUploader = $container->get(FileUploader::class);
    $fileUploader->getFileExtensions('Graphics/Design');

    // Move attached file, if there is one
    if (!empty($_FILES['headerImageFile']['tmp_name'])) {
        $file = $_FILES['headerImageFile'] ?? null;

        // Upload the file, return the /uploads relative path
        $data['headerImage'] = $fileUploader->uploadAndResizeImage($file, $data['name'], 2048, 90);

        if (empty($data['headerImage'])) {
            $partialFail = true;
        }

    } else {
        $data['headerImage'] = $_POST['headerImage'] ?? '';
    }

    // Update the record
    $updated = $unitGateway->update($deepLearningUnitID, $data);
    $partialFail = !$updated;
    
    // Update the authors
    $authors = $_POST['authors'] ?? '';
    $authorIDs = [];
    foreach ($authors as $person) {
        $authorData = [
            'deepLearningUnitID' => $deepLearningUnitID,
            'gibbonPersonID'     => $person['gibbonPersonID'],
        ];

        if ($person['gibbonPersonID'] == $session->get('gibbonPersonID')) {
            $authorData['timestamp'] = date('Y-m-d H:i:s');
        } else if ($person['gibbonPersonIDOriginal'] != $person['gibbonPersonID']) {
            $authorData['timestamp'] = null;
        }
        
        $deepLearningUnitAuthorID = $person['deepLearningUnitAuthorID'] ?? '';

        if (!empty($deepLearningUnitAuthorID)) {
            $partialFail &= !$unitAuthorGateway->update($deepLearningUnitAuthorID, $authorData);
        } else {
            $deepLearningUnitAuthorID = $unitAuthorGateway->insert($authorData);
            $partialFail &= !$deepLearningUnitAuthorID;
        }

        $authorIDs[] = str_pad($deepLearningUnitAuthorID, 12, '0', STR_PAD_LEFT);
    }

    // Cleanup authors that have been deleted
    $unitAuthorGateway->deleteAuthorsNotInList($deepLearningUnitID, $authorIDs);

    // Update the itinerary blocks
    $blockOrder = $_POST['blockOrder'] ?? [];
    $blockSequence = 0;
    $blockIDs = [];

    foreach ($blockOrder as $index) {
        $blockData = [
            'deepLearningUnitID' => $deepLearningUnitID,
            'title'              => $_POST["title{$index}"] ?? '',
            'content'            => $_POST["content{$index}"] ?? '',
            'type'               => $_POST["type{$index}"] ?? 'Main',
            'sequenceNumber'     => $blockSequence,
        ];

        if (empty($blockData['title']) && empty($blockData['content'])) {
            continue;
        }

        $deepLearningUnitBlockID = $_POST["deepLearningUnitBlockID{$index}"] ?? '';

        if (!empty($deepLearningUnitBlockID)) {
            $partialFail &= !$unitBlockGateway->update($deepLearningUnitBlockID, $blockData);
        } else {
            $deepLearningUnitBlockID = $unitBlockGateway->insert($blockData);
            $partialFail &= !$deepLearningUnitBlockID;
        }

        $blockIDs[] = str_pad($deepLearningUnitBlockID, 12, '0', STR_PAD_LEFT);
        $blockSequence++;
    }

    // Cleanup blocks that have been deleted
    $unitBlockGateway->deleteBlocksNotInList($deepLearningUnitID, $blockIDs);

    // Update the photos
    $photos = $_POST['photos'] ?? [];
    $photoOrder = $_POST['order'] ?? [];
    $photoSequence = max($photoOrder) + 1;
    $photoIDs = [];

    foreach ($photos as $index => $photo) {

        $photoData = [
            'deepLearningUnitID' => $deepLearningUnitID,
            'filePath'           => $photo['filePath'] ?? '',
            'caption'            => $photo['caption'] ?? '',
            'sequenceNumber'     => array_search($index, $photoOrder) ?? false,
        ];

        if (!empty($_FILES['photos']['tmp_name'][$index]['fileUpload'])) {
            $file = [
                'name' => $_FILES['photos']['name'][$index]['fileUpload'] ?? '',
                'type' => $_FILES['photos']['type'][$index]['fileUpload'] ?? '',
                'tmp_name' => $_FILES['photos']['tmp_name'][$index]['fileUpload'] ?? '',
                'error' => $_FILES['photos']['error'][$index]['fileUpload'] ?? '',
                'size' => $_FILES['photos']['size'][$index]['fileUpload'] ?? '',
            ];
    
            // Upload the file, return the /uploads relative path
            $photoData['filePath'] = $fileUploader->uploadAndResizeImage($file, $data['name'], 1024, 80);
        }

        if (empty($photoData['filePath'])) {
            $partialFail = true;
            continue;
        }

        if ($photoData['sequenceNumber'] === false) {
            $photoData['sequenceNumber'] = $photoSequence;
            $photoSequence++;
        }

        $deepLearningUnitPhotoID = $photo['deepLearningUnitPhotoID'] ?? '';

        if (!empty($deepLearningUnitPhotoID)) {
            $partialFail &= !$unitPhotoGateway->update($deepLearningUnitPhotoID, $photoData);
        } else {
            $deepLearningUnitPhotoID = $unitPhotoGateway->insert($photoData);
            $partialFail &= !$deepLearningUnitPhotoID;
        }

        $photoIDs[] = str_pad($deepLearningUnitPhotoID, 12, '0', STR_PAD_LEFT);
    }

    // Remove photos that have been deleted from the filesystem
    $cleanupPhotos = $unitPhotoGateway->selectPhotosNotInList($deepLearningUnitID, $photoIDs)->fetchAll();
    foreach ($cleanupPhotos as $photo) {
        $unitPhotoGateway->delete($photo['deepLearningUnitPhotoID']);

        $photoPath = $session->get('absolutePath').'/'.$photo['filePath'];
        if (!empty($photo['filePath']) && file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    // Update the tags
    $tags = array_unique(array_filter(array_merge(explode(',', $data['majors'] ?? ''), explode(',', $data['minors'] ?? ''))));
    foreach ($tags as $tag) {
        $unitTagGateway->insertAndUpdate(['tag' => $tag], ['tag' => $tag]);
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
