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

namespace Gibbon\Module\DeepLearning\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class ChoiceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningChoice';
    private static $primaryKey = 'deepLearningChoiceID';
    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'deepLearningEvent.name', 'deepLearningExperience.name'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChoices(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name as eventName',
                'deepLearningEvent.nameShort as eventNameShort',
                'deepLearningChoice.gibbonPersonID',
                'deepLearningChoice.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonFormGroup.nameShort as formGroup',
                'gibbonYearGroup.nameShort as yearGroup',
                "GROUP_CONCAT(deepLearningExperience.name ORDER BY deepLearningChoice.choice SEPARATOR ',') as choices",
                "GROUP_CONCAT(CONCAT(deepLearningChoice.choice, ':', deepLearningExperience.name) ORDER BY deepLearningChoice.choice SEPARATOR ',') as choiceList",
                "(CASE WHEN deepLearningEnrolment.deepLearningEnrolmentID IS NOT NULL THEN enrolledExperience.name ELSE '' END) as enrolledExperience"
                
            ])
            ->innerJoin('deepLearningExperience', 'deepLearningExperience.deepLearningExperienceID=deepLearningChoice.deepLearningExperienceID')
            ->innerJoin('deepLearningEvent', 'deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=deepLearningChoice.gibbonPersonID')
            ->leftJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID')
            ->leftJoin('gibbonFormGroup', 'gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID')
            ->leftJoin('gibbonYearGroup', 'gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID')
            ->leftJoin('deepLearningEnrolment', 'deepLearningEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND deepLearningEnrolment.deepLearningEventID=deepLearningEvent.deepLearningEventID')
            ->leftJoin('deepLearningExperience as enrolledExperience', 'enrolledExperience.deepLearningExperienceID=deepLearningEnrolment.deepLearningExperienceID')
            ->where('deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['deepLearningChoice.gibbonPersonID']);


        $criteria->addFilterRules([
            'event' => function ($query, $deepLearningEventID) {
                return $query
                    ->where('deepLearningEvent.deepLearningEventID = :deepLearningEventID')
                    ->bindValue('deepLearningEventID', $deepLearningEventID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChoicesByPerson(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name as eventName',
                'deepLearningEvent.nameShort as eventNameShort',
                'deepLearningChoice.gibbonPersonID',
                'deepLearningChoice.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                "GROUP_CONCAT(deepLearningExperience.name ORDER BY deepLearningChoice.choice SEPARATOR ',') as choices",
                
            ])
            ->innerJoin('deepLearningExperience', 'deepLearningExperience.deepLearningExperienceID=deepLearningChoice.deepLearningExperienceID')
            ->innerJoin('deepLearningEvent', 'deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=deepLearningChoice.gibbonPersonID')
            ->where('deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('deepLearningChoice.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['deepLearningChoice.gibbonPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryNotSignedUpStudentsByEvent($criteria, $deepLearningEventID)
    {
        $query = $this
            ->newQuery()
            ->from('deepLearningEvent')
            ->cols([
                'gibbonStudentEnrolment.gibbonPersonID as groupBy',
                '0 as deepLearningExperienceID',
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name as eventName',
                'deepLearningEvent.nameShort as eventNameShort',
                'gibbonStudentEnrolment.gibbonPersonID',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
                'gibbonPerson.email',
                'gibbonPerson.image_240',
                'gibbonFormGroup.name as formGroup',
                'gibbonYearGroup.name as yearGroup',
                'gibbonYearGroup.sequenceNumber as yearGroupSequence',
            ])
            ->innerJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID')
            ->innerJoin('gibbonYearGroup', 'gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID')
            ->innerJoin('gibbonFormGroup', 'gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID')

            ->leftJoin('deepLearningChoice', 'deepLearningChoice.deepLearningEventID=deepLearningEvent.deepLearningEventID AND deepLearningChoice.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('deepLearningExperience', 'deepLearningExperience.deepLearningExperienceID=deepLearningChoice.deepLearningExperienceID')

            ->where('deepLearningEvent.deepLearningEventID=:deepLearningEventID')
            ->bindValue('deepLearningEventID', $deepLearningEventID)
            ->where('FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningEvent.gibbonYearGroupIDList)')
            ->where("gibbonPerson.status = 'Full'")
            ->where('(gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart <= :today)')
            ->where('(gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->where('deepLearningChoice.deepLearningChoiceID IS NULL')
            ->groupBy(['gibbonPerson.gibbonPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectChoiceCountsByEvent($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID, 'today' => date('Y-m-d')];
        $sql = "SELECT deepLearningExperience.deepLearningExperienceID as groupBy,
                    deepLearningExperience.deepLearningExperienceID,
                    COUNT(DISTINCT CASE WHEN deepLearningChoice.choice=1 AND gibbonPerson.gibbonPersonID IS NOT NULL THEN deepLearningChoice.deepLearningChoiceID END) as choice1,
                    COUNT(DISTINCT CASE WHEN deepLearningChoice.choice=2 AND gibbonPerson.gibbonPersonID IS NOT NULL THEN deepLearningChoice.deepLearningChoiceID END) as choice2,
                    COUNT(DISTINCT CASE WHEN deepLearningChoice.choice=3 AND gibbonPerson.gibbonPersonID IS NOT NULL THEN deepLearningChoice.deepLearningChoiceID END) as choice3,
                    COUNT(DISTINCT CASE WHEN deepLearningChoice.choice=4 AND gibbonPerson.gibbonPersonID IS NOT NULL THEN deepLearningChoice.deepLearningChoiceID END) as choice4,
                    COUNT(DISTINCT CASE WHEN deepLearningChoice.choice=5 AND gibbonPerson.gibbonPersonID IS NOT NULL THEN deepLearningChoice.deepLearningChoiceID END) as choice5
                FROM deepLearningExperience
                LEFT JOIN deepLearningChoice ON (deepLearningChoice.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID)
                LEFT JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=deepLearningChoice.gibbonPersonID AND gibbonPerson.status = 'Full' AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart <= :today)
                AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd >= :today))
                WHERE deepLearningExperience.deepLearningEventID=:deepLearningEventID
                GROUP BY deepLearningExperience.deepLearningExperienceID";

        return $this->db()->select($sql, $data);
    }

    public function selectChoicesByEvent($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID, 'today' => date('Y-m-d')];
        $sql = "SELECT deepLearningChoice.gibbonPersonID as groupBy,
                    deepLearningChoice.gibbonPersonID,
                    deepLearningChoice.timestampCreated,
                    gibbonPerson.surname,
                    gibbonPerson.preferredName,
                    gibbonFormGroup.name as formGroup,
                    gibbonYearGroup.sequenceNumber as yearGroupSequence,
                    MIN(CASE WHEN deepLearningChoice.choice=1 THEN deepLearningChoice.deepLearningExperienceID END) as choice1,
                    MIN(CASE WHEN deepLearningChoice.choice=2 THEN deepLearningChoice.deepLearningExperienceID END) as choice2,
                    MIN(CASE WHEN deepLearningChoice.choice=3 THEN deepLearningChoice.deepLearningExperienceID END) as choice3,
                    MIN(CASE WHEN deepLearningChoice.choice=4 THEN deepLearningChoice.deepLearningExperienceID END) as choice4,
                    MIN(CASE WHEN deepLearningChoice.choice=5 THEN deepLearningChoice.deepLearningExperienceID END) as choice5
                FROM deepLearningChoice
                JOIN deepLearningEvent ON (deepLearningEvent.deepLearningEventID=deepLearningChoice.deepLearningEventID)
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=deepLearningChoice.gibbonPersonID)
                LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID)
                LEFT JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                LEFT JOIN gibbonYearGroup ON (gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID)
                WHERE deepLearningEvent.deepLearningEventID=:deepLearningEventID
                AND gibbonPerson.status = 'Full'
                AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart <= :today)
                AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd >= :today)
                GROUP BY deepLearningChoice.gibbonPersonID
                ORDER BY gibbonFormGroup.name, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectChoiceWeightingByEvent($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID];
        $sql = "SELECT deepLearningChoice.gibbonPersonID as groupBy,
                    deepLearningChoice.gibbonPersonID,
                    SUM(pastChoice.choice) as choiceCount,
                    COUNT(DISTINCT pastChoice.deepLearningEventID) as eventCount
                FROM deepLearningChoice
                JOIN deepLearningEvent ON (deepLearningEvent.deepLearningEventID=deepLearningChoice.deepLearningEventID)
                LEFT JOIN deepLearningEnrolment AS pastEnrolment ON (pastEnrolment.gibbonPersonID=deepLearningChoice.gibbonPersonID AND pastEnrolment.deepLearningEventID<>deepLearningEvent.deepLearningEventID AND pastEnrolment.status='Confirmed')
                LEFT JOIN deepLearningChoice as pastChoice ON (pastChoice.deepLearningChoiceID=pastEnrolment.deepLearningChoiceID AND pastChoice.gibbonPersonID=deepLearningChoice.gibbonPersonID )
                WHERE deepLearningEvent.deepLearningEventID=:deepLearningEventID
                AND deepLearningChoice.choice=1
                GROUP BY deepLearningChoice.gibbonPersonID
                ORDER BY choiceCount DESC";

        return $this->db()->select($sql, $data);
    }

    public function getYearGroupWeightingMax()
    {
        return $this->db()->selectOne("SELECT MAX(sequenceNumber) FROM gibbonYearGroup");
    }

    public function selectChoicesByPerson($deepLearningEventID, $gibbonPersonID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT deepLearningChoice.choice as groupBy, deepLearningChoice.*
                FROM deepLearningChoice
                JOIN deepLearningExperience ON (deepLearningExperience.deepLearningExperienceID=deepLearningChoice.deepLearningExperienceID)
                WHERE deepLearningExperience.deepLearningEventID=:deepLearningEventID
                AND deepLearningChoice.gibbonPersonID=:gibbonPersonID
                ORDER BY deepLearningChoice.choice";

        return $this->db()->select($sql, $data);
    }

    public function getChoiceByExperienceAndPerson($deepLearningExperienceID, $gibbonPersonID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT *
                FROM deepLearningChoice
                WHERE deepLearningChoice.deepLearningExperienceID=:deepLearningExperienceID
                AND deepLearningChoice.gibbonPersonID=:gibbonPersonID
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function deleteChoicesNotInList($deepLearningEventID, $gibbonPersonID, $choiceIDs)
    {
        $choiceIDs = is_array($choiceIDs) ? implode(',', $choiceIDs) : $choiceIDs;

        $data = ['deepLearningEventID' => $deepLearningEventID, 'gibbonPersonID' => $gibbonPersonID, 'choiceIDs' => $choiceIDs];
        $sql = "DELETE FROM deepLearningChoice 
                WHERE deepLearningEventID=:deepLearningEventID 
                AND gibbonPersonID=:gibbonPersonID
                AND NOT FIND_IN_SET(deepLearningChoiceID, :choiceIDs)";

        return $this->db()->delete($sql, $data);
    }

}
