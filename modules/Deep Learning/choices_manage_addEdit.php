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
use Gibbon\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/choices_manage_addEdit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $params = [
        'mode'                => $_REQUEST['mode'] ?? '',
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? '',
        'gibbonPersonID'      => $_REQUEST['gibbonPersonID'] ?? '',
    ];

    $page->breadcrumbs
        ->add(__m('Manage Choices'), 'choices_manage.php')
        ->add($params['mode'] == 'add' ? __m('Add Choices') : __m('Edit Choices'));
     
    $page->return->addReturns([
        'error4' => __m('Sign up is currently not available for this Deep Learning event.'),
        'error5' => __m('There was an error verifying your Deep Learning choices. Please try again.'),
    ]);

    if ($params['mode'] == 'add' && isset($_GET['editID']) && isset($_GET['editID2'])) {
        $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/choices_manage_addEdit.php&mode=edit&deepLearningEventID='.$_GET['editID'].'&gibbonPersonID='.$_GET['editID2']);
    }

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $choiceGateway = $container->get(ChoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Get events and experiences
    $events = $eventGateway->selectAllEvents($session->get('gibbonSchoolYearID'));
    $experiences = $experienceGateway->selectExperiences()->fetchAll();

    $experienceList = array_combine(array_column($experiences, 'deepLearningExperienceID'), array_column($experiences, 'name'));
    $experienceChainedTo = array_combine(array_column($experiences, 'deepLearningExperienceID'), array_column($experiences, 'deepLearningEventID'));

    $signUpChoices = $settingGateway->getSettingByScope('Deep Learning', 'signUpChoices');

    $choiceList = [1 => __m('First Choice'), 2 => __m('Second Choice'), 3 => __m('Third Choice'), 4 => __m('Fourth Choice'), 5 => __m('Fifth Choice')];

    if ($params['mode'] == 'edit') {    
        $choices = $choiceGateway->selectChoicesByPerson($params['deepLearningEventID'], $params['gibbonPersonID'])->fetchGroupedUnique();
        $choice = [];
        for ($i = 1; $i <= $signUpChoices; $i++) {
            $choice[$i] = $choices[$i]['deepLearningExperienceID'] ?? $choices[$i-1] ?? '';
        }
    }

    // FORM
    $form = Form::create('choices', $session->get('absoluteURL').'/modules/'.$session->get('module').'/choices_manage_addEditProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('mode', $params['mode']);
    
    $row = $form->addRow();
        $row->addLabel('deepLearningEventID', __('Event'));
        $row->addSelect('deepLearningEventID')
            ->fromResults($events, 'groupBy')
            ->required()
            ->placeholder()
            ->selected($params['deepLearningEventID'])
            ->readOnly($params['mode'] == 'edit');

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Person'));
        $row->addSelectUsers('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['includeStudents' => true])
            ->required()
            ->placeholder()
            ->selected($params['mode'] == 'edit' ? $params['gibbonPersonID'] : '')
            ->readOnly($params['mode'] == 'edit');

    for ($i = 1; $i <= $signUpChoices; $i++) {
        $row = $form->addRow();
        $row->addLabel("choices[{$i}]", $choiceList[$i] ?? $i);
        $row->addSelect("choices[{$i}]")
            ->fromArray($experienceList)
            ->setID("choices{$i}")
            ->addClass('choicesChoice')
            ->chainedTo('deepLearningEventID', $experienceChainedTo)
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
$(document).on('change input', '.choicesChoice', function () {
    var currentChoice = this;

    $('.choicesChoice').not(this).each(function() {
        if ($(currentChoice).val() == $(this).val()) {
            $(this).val($(this).find("option:first-child").val());
        }
    });
});
</script>
