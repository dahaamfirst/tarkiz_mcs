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

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/view_experience_signUp.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $deepLearningEventID = $_REQUEST['deepLearningEventID'] ?? '';
    $deepLearningExperienceID = $_REQUEST['deepLearningExperienceID'] ?? '';

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $choiceGateway = $container->get(ChoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    if (empty($deepLearningEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $event = $eventGateway->getEventDetailsByID($deepLearningEventID);

    if (empty($event)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Check that sign up is open based on the date
    $signUpIsOpen = false;
    if (!empty($event['accessOpenDate']) && !empty($event['accessCloseDate'])) {
        $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $event['accessOpenDate'])->format('U');
        $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $event['accessCloseDate'])->format('U');
        $now = (new DateTime('now'))->format('U');

        $signUpIsOpen = $accessOpenDate <= $now && $accessCloseDate >= $now;
    }

    if (!$signUpIsOpen) {
        $page->addError(__m('Sign up is currently not available for this Deep Learning event.'));
        return;
    }
    
    // Check the student's sign up access based on their year group
    $signUpAccess = $eventGateway->getEventSignUpAccess($deepLearningEventID, $session->get('gibbonPersonID'));
    if (!$signUpAccess) {
        $page->addError(__m('Sign up is currently not available for this Deep Learning event.'));
        return;
    }

    // Get experiences
    $experiences = $experienceGateway->selectExperiencesByEventAndPerson($deepLearningEventID, $session->get('gibbonPersonID'))->fetchKeyPair();
    $choicesSelected = $choiceGateway->selectChoicesByPerson($deepLearningEventID, $session->get('gibbonPersonID'))->fetchGroupedUnique();

    $signUpText = $settingGateway->getSettingByScope('Deep Learning', 'signUpText');
    $signUpChoices = $settingGateway->getSettingByScope('Deep Learning', 'signUpChoices');

    // Lower the choice limit if there are less options
    if (count($experiences) < $signUpChoices) {
        $signUpChoices = count($experiences);
    }

    $choiceList = [1 => __m('First Choice'), 2 => __m('Second Choice'), 3 => __m('Third Choice'), 4 => __m('Fourth Choice'), 5 => __m('Fifth Choice')];
    $choice = [];
    for ($i = 1; $i <= $signUpChoices; $i++) {
        $choice[$i] = $choicesSelected[$i]['deepLearningExperienceID'] ?? '';
        if ($i == 1 && empty($choice[$i])) $choice[$i] = $deepLearningExperienceID;
    }
    
    // FORM
    $form = Form::create('event', $session->get('absoluteURL').'/modules/'.$session->get('module').'/view_experience_signUpProcess.php');
    $form->setTitle(__m("Deep Learning Sign Up"));
    $form->setDescription($signUpText);

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonPersonID', $session->get('gibbonPersonID'));
    $form->addHiddenValue('deepLearningEventID', $deepLearningEventID);
    $form->addHiddenValue('deepLearningExperienceID', $deepLearningExperienceID);

    for ($i = 1; $i <= $signUpChoices; $i++) {
        $row = $form->addRow();
        $row->addLabel("choices[{$i}]", $choiceList[$i] ?? $i);
        $row->addSelect("choices[{$i}]")
            ->fromArray($experiences)
            ->setID("choices{$i}")
            ->addClass('signUpChoice')
            ->required()
            ->placeholder()
            ->selected($choice[$i] ?? '');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
?>

<script>
$(document).on('change input', '.signUpChoice', function () {
    var currentChoice = this;

    $('.signUpChoice').not(this).each(function() {
        if ($(currentChoice).val() == $(this).val()) {
            $(this).val($(this).find("option:first-child").val());
        }
    });
});
</script>
