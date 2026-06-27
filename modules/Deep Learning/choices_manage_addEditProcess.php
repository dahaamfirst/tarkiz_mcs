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
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'mode'                => $_POST['mode'] ?? '',
    'deepLearningEventID' => $_POST['deepLearningEventID'] ?? '',
    'gibbonPersonID'      => $_POST['gibbonPersonID'] ?? '',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/choices_manage_addEdit.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/choices_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $choiceGateway = $container->get(ChoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $choices = $_POST['choices'] ?? [];

    // Validate the required values are present
    if (empty($choices) || empty($params['gibbonPersonID']) || empty($params['deepLearningEventID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $event = $eventGateway->getEventDetailsByID($params['deepLearningEventID']);
    if (empty($event)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $existingChoices = $choiceGateway->selectChoicesByPerson($params['deepLearningEventID'], $params['gibbonPersonID'])->fetchGroupedUnique();
    $signUpChoices = $settingGateway->getSettingByScope('Deep Learning', 'signUpChoices');

    // Update the sign up choices
    $choiceIDs = [];
    foreach ($choices as $choice => $deepLearningExperienceID) {
        $choice = intval($choice);

        // Validate the experience selected
        if (!$experienceGateway->exists($deepLearningExperienceID)) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
            exit;
        }

        // Validate the choice number selected
        if ($choice <= 0 || $choice > $signUpChoices) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
            exit;
        }

        // Prepare data to insert or update
        $choicesData = [
            'deepLearningExperienceID' => $deepLearningExperienceID,
            'deepLearningEventID'      => $params['deepLearningEventID'],
            'gibbonPersonID'           => $params['gibbonPersonID'],
            'choice'                   => $choice,
            'timestampModified'        => date('Y-m-d H:i:s'),
            'gibbonPersonIDModified'   => $session->get('gibbonPersonID'),
        ];

        $deepLearningChoiceID = $existingChoices[$choice]['deepLearningChoiceID'] ?? '';

        if (!empty($deepLearningChoiceID)) {
            $partialFail &= !$choiceGateway->update($deepLearningChoiceID, $choicesData);
        } else {
            $choicesData['timestampCreated'] = date('Y-m-d H:i:s');
            $choicesData['gibbonPersonIDCreated'] = $session->get('gibbonPersonID');

            $deepLearningChoiceID = $choiceGateway->insert($choicesData);
            $partialFail &= !$deepLearningChoiceID;
        }

        $choiceIDs[] = str_pad($deepLearningChoiceID, 12, '0', STR_PAD_LEFT);
    }

    // Cleanup sign ups that have been deleted
    $choiceGateway->deleteChoicesNotInList($params['deepLearningEventID'], $params['gibbonPersonID'], $choiceIDs);

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header($params['mode'] == 'add'
        ? "Location: {$URL}&editID={$params['deepLearningEventID']}&editID2={$params['gibbonPersonID']}"
        : "Location: {$URL}"
    );
}
