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

class ExperienceTripGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningExperienceTrip';
    private static $primaryKey = 'deepLearningExperienceTripID';
    private static $searchableColumns = [];

    /**
     * @param QueryCriteria $criteria
     * @param string $deepLearningEventID
     * @return DataSet
     */
    public function queryTripsByExperience(QueryCriteria $criteria, $deepLearningExperienceID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tripPlannerRequests.tripPlannerRequestID',
                'tripPlannerRequests.creatorPersonID',
                'tripPlannerRequests.title as tripTitle',
                'tripPlannerRequests.description',
                'tripPlannerRequests.location',
                'tripPlannerRequests.status',
                'tripPlannerRequests.messengerGroupID',
                'gibbonPerson.title',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                '(SELECT startDate FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID ORDER BY startDate ASC LIMIT 1) as firstDayOfTrip',
            ])
            ->from($this->getTableName())
            ->innerJoin('tripPlannerRequests', 'tripPlannerRequests.tripPlannerRequestID=deepLearningExperienceTrip.tripPlannerRequestID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = tripPlannerRequests.creatorPersonID')
            ->innerJoin('deepLearningExperience', 'deepLearningExperience.deepLearningExperienceID=deepLearningExperienceTrip.deepLearningExperienceID')
            ->innerJoin('deepLearningEvent', 'deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID')
            ->where('deepLearningExperienceTrip.deepLearningExperienceID=:deepLearningExperienceID')
            ->bindValue('deepLearningExperienceID', $deepLearningExperienceID);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('deepLearningExperience.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function getTripPlannerModule()
    {
        $sql = "SELECT gibbonModuleID FROM gibbonModule WHERE name='Trip Planner' AND active='Y'";
        return $this->db()->selectOne($sql);
    }

    public function selectTripRequests($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT tripPlannerRequestID as value, CONCAT(tripPlannerRequests.title, ' (', gibbonPerson.preferredName, ' ' , gibbonPerson.surname,')') as name
                FROM tripPlannerRequests 
                JOIN gibbonPerson ON (tripPlannerRequests.creatorPersonID=gibbonPerson.gibbonPersonID)
                WHERE tripPlannerRequests.gibbonSchoolYearID=:gibbonSchoolYearID
                AND (tripPlannerRequests.status = 'Approved' OR tripPlannerRequests.status = 'Requested' OR tripPlannerRequests.status = 'Awaiting Final Approval' OR tripPlannerRequests.status = 'Draft')
                AND (SELECT IFNULL(MAX(endDate),CURRENT_DATE) FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID) >= CURRENT_DATE
                ORDER BY tripPlannerRequests.title";

        return $this->db()->select($sql, $data);
    }

    public function selectDeepLearningTripRequests($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT deepLearningExperienceTrip.deepLearningExperienceID, tripPlannerRequests.tripPlannerRequestID
                FROM deepLearningExperienceTrip
                JOIN tripPlannerRequests ON (deepLearningExperienceTrip.tripPlannerRequestID=tripPlannerRequests.tripPlannerRequestID)
                
                WHERE tripPlannerRequests.gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->select($sql, $data);
    }

    public function selectTripRequestsByCreator($gibbonSchoolYearID, $gibbonPersonID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT tripPlannerRequestID as value, tripPlannerRequests.title as name
                FROM tripPlannerRequests 
                WHERE tripPlannerRequests.creatorPersonID=:gibbonPersonID
                AND tripPlannerRequests.gibbonSchoolYearID=:gibbonSchoolYearID
                AND (tripPlannerRequests.status = 'Approved' OR tripPlannerRequests.status = 'Requested' OR tripPlannerRequests.status = 'Awaiting Final Approval' OR tripPlannerRequests.status = 'Draft')
                AND (SELECT IFNULL(MAX(endDate),CURRENT_DATE) FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID) >= CURRENT_DATE
                ORDER BY tripPlannerRequests.title";

        return $this->db()->select($sql, $data);
    }

    public function syncTripStaff($deepLearningExperienceID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "DELETE `tripPlannerRequestPerson` 
            FROM `tripPlannerRequestPerson` 
            JOIN tripPlannerRequests ON (tripPlannerRequests.tripPlannerRequestID=tripPlannerRequestPerson.tripPlannerRequestID)
            JOIN deepLearningExperience ON (deepLearningExperience.deepLearningExperienceID=tripPlannerRequests.deepLearningExperienceID)
            LEFT JOIN deepLearningStaff ON (deepLearningStaff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND tripPlannerRequestPerson.gibbonPersonID=deepLearningStaff.gibbonPersonID)
            WHERE tripPlannerRequests.deepLearningExperienceID=:deepLearningExperienceID
            AND tripPlannerRequests.deepLearningSync='Y'
            AND tripPlannerRequestPerson.role='Teacher'
            AND deepLearningStaff.deepLearningStaffID IS NULL
        ";

        $this->db()->statement($sql, $data);

        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "INSERT INTO `tripPlannerRequestPerson` (`tripPlannerRequestID`, `gibbonPersonID`, `role`) 
            SELECT tripPlannerRequests.tripPlannerRequestID, deepLearningStaff.gibbonPersonID, 'Teacher'
            FROM tripPlannerRequests
            JOIN deepLearningExperience ON (deepLearningExperience.deepLearningExperienceID=tripPlannerRequests.deepLearningExperienceID)
            JOIN deepLearningStaff ON (deepLearningStaff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID)
            LEFT JOIN tripPlannerRequestPerson ON (tripPlannerRequestPerson.tripPlannerRequestID=tripPlannerRequests.tripPlannerRequestID AND tripPlannerRequestPerson.gibbonPersonID=deepLearningStaff.gibbonPersonID)
            WHERE tripPlannerRequests.deepLearningExperienceID=:deepLearningExperienceID
            AND tripPlannerRequests.deepLearningSync='Y'
            AND tripPlannerRequestPerson.tripPlannerRequestPersonID IS NULL
        ";

        return $this->db()->statement($sql, $data);
    }

    public function syncTripStudents($deepLearningExperienceID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "DELETE `tripPlannerRequestPerson` 
            FROM `tripPlannerRequestPerson` 
            JOIN tripPlannerRequests ON (tripPlannerRequests.tripPlannerRequestID=tripPlannerRequestPerson.tripPlannerRequestID)
            JOIN deepLearningExperience ON (deepLearningExperience.deepLearningExperienceID=tripPlannerRequests.deepLearningExperienceID)
            LEFT JOIN deepLearningEnrolment ON (deepLearningEnrolment.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND tripPlannerRequestPerson.gibbonPersonID=deepLearningEnrolment.gibbonPersonID)
            WHERE tripPlannerRequests.deepLearningExperienceID=:deepLearningExperienceID
            AND tripPlannerRequests.deepLearningSync='Y'
            AND tripPlannerRequestPerson.role='Student'
            AND deepLearningEnrolment.deepLearningEnrolmentID IS NULL
        ";

        $this->db()->statement($sql, $data);

        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "INSERT INTO `tripPlannerRequestPerson` (`tripPlannerRequestID`, `gibbonPersonID`, `role`) 
            SELECT tripPlannerRequests.tripPlannerRequestID, deepLearningEnrolment.gibbonPersonID, 'Student'
            FROM tripPlannerRequests
            JOIN deepLearningExperience ON (deepLearningExperience.deepLearningExperienceID=tripPlannerRequests.deepLearningExperienceID)
            JOIN deepLearningEnrolment ON (deepLearningEnrolment.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID)
            LEFT JOIN tripPlannerRequestPerson ON (tripPlannerRequestPerson.tripPlannerRequestID=tripPlannerRequests.tripPlannerRequestID AND tripPlannerRequestPerson.gibbonPersonID=deepLearningEnrolment.gibbonPersonID)
            WHERE tripPlannerRequests.deepLearningExperienceID=:deepLearningExperienceID
            AND tripPlannerRequests.deepLearningSync='Y'
            AND tripPlannerRequestPerson.tripPlannerRequestPersonID IS NULL
        ";

        return $this->db()->statement($sql, $data);
    }

    public function syncTripGroups($deepLearningExperienceID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "DELETE `gibbonGroupPerson` 
            FROM `gibbonGroupPerson` 
            JOIN tripPlannerRequests ON (tripPlannerRequests.messengerGroupID=gibbonGroupPerson.gibbonGroupID)
            LEFT JOIN tripPlannerRequestPerson ON (tripPlannerRequests.tripPlannerRequestID=tripPlannerRequestPerson.tripPlannerRequestID AND tripPlannerRequestPerson.gibbonPersonID=gibbonGroupPerson.gibbonPersonID)
            WHERE tripPlannerRequests.deepLearningExperienceID=:deepLearningExperienceID
            AND tripPlannerRequests.deepLearningSync='Y'
            AND tripPlannerRequests.messengerGroupID IS NOT NULL
            AND tripPlannerRequestPerson.tripPlannerRequestPersonID IS NULL
        ";

        $this->db()->statement($sql, $data);

        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "INSERT INTO `gibbonGroupPerson` (`gibbonGroupID`, `gibbonPersonID`) 
            SELECT tripPlannerRequests.messengerGroupID, tripPlannerRequestPerson.gibbonPersonID
            FROM tripPlannerRequests
            JOIN tripPlannerRequestPerson ON (tripPlannerRequests.tripPlannerRequestID=tripPlannerRequestPerson.tripPlannerRequestID)
            LEFT JOIN gibbonGroupPerson as groupPerson ON (groupPerson.gibbonGroupID=tripPlannerRequests.messengerGroupID AND groupPerson.gibbonPersonID=tripPlannerRequestPerson.gibbonPersonID)
            WHERE tripPlannerRequests.deepLearningExperienceID=:deepLearningExperienceID
            AND tripPlannerRequests.deepLearningSync='Y'
            AND tripPlannerRequests.messengerGroupID IS NOT NULL
            AND groupPerson.gibbonGroupPersonID IS NULL
        ";

        return $this->db()->statement($sql, $data);
    }

    public function attachTripRequest($deepLearningExperienceID, $tripPlannerRequestID, $deepLearningEventDateIDList = null)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID, 'tripPlannerRequestID' => $tripPlannerRequestID, 'deepLearningEventDateIDList' => $deepLearningEventDateIDList];
        $this->insertAndUpdate($data, ['deepLearningEventDateIDList' => $deepLearningEventDateIDList]);

        $data = ['deepLearningExperienceID' => $deepLearningExperienceID, 'tripPlannerRequestID' => $tripPlannerRequestID];
        $sql = "UPDATE tripPlannerRequests SET deepLearningExperienceID=:deepLearningExperienceID WHERE tripPlannerRequestID=:tripPlannerRequestID";
        $this->db()->update($sql, $data);

        $data = ['tripPlannerRequestID' => $tripPlannerRequestID];
        $sql = "SELECT * FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
        return $this->db()->selectOne($sql, $data);
    }

    public function insertTripRequest($data)
    {
        $query = $this
            ->newInsert()
            ->into('tripPlannerRequests')
            ->cols($data);

        return $this->runInsert($query);
    }

    public function updateTripRequest($primaryKeyValue, $data)
    {
        $query = $this
            ->newUpdate()
            ->table('tripPlannerRequests')
            ->cols($data)
            ->where('tripPlannerRequestID = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runUpdate($query);
    }

    public function insertTripPerson($data)
    {
        $query = $this
            ->newInsert()
            ->into('tripPlannerRequestPerson')
            ->cols($data);

        return $this->runInsert($query);
    }

    public function insertTripDays($data)
    {
        $query = $this
            ->newInsert()
            ->into('tripPlannerRequestDays')
            ->cols($data);

        return $this->runInsert($query);
    }

    public function insertGroup($data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonGroup')
            ->cols($data);

        return $this->runInsert($query);
    }

    public function insertGroupPerson($data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonGroupPerson')
            ->cols($data);

        return $this->runInsert($query);
    }
}
