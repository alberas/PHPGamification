<?php
/**
 * Class PHPGamificationDAO
 * Default DAO to use PHPGamification, recommended to be used
 * Data Access Object for gamification persistent data layer
 */

namespace TiagoGouvea\PHPGamification;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionClass;
use TiagoGouvea\PHPGamification\Model\DAOInterface;
use TiagoGouvea\PHPGamification\Model\Badge;
use TiagoGouvea\PHPGamification\Model\Event;
use TiagoGouvea\PHPGamification\Model\Level;
use TiagoGouvea\PHPGamification\Model\UserAlert;
use TiagoGouvea\PHPGamification\Model\UserBadge;
use TiagoGouvea\PHPGamification\Model\UserEvent;
use TiagoGouvea\PHPGamification\Model\UserLog;
use TiagoGouvea\PHPGamification\Model\UserScore;

class DAO implements DAOInterface
{
    /* @var $conn PDO */
    public $conn = null;

    public function __construct($host, $dbname, $username, $password)
    {
        if (!$this->conn) {
            try {
                $this->conn = new PDO('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8', $username, $password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            } catch (PDOException $e) {
                exit($e->getMessage());
            }
        }
    }

    /**
     * @return null|PDO
     */
    public function getConnection()
    {

        return $this->conn;
    }

    public function execute($sql, $params = array())
    {
//        echo "<br><b>$sql</b><br>";echo print_r($params);
        /** @var PDOStatement $stmt */
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function lastInsertId()
    {
        $conn = $this->getConnection();
        return $conn->lastInsertId();
    }

    /**
     * @param $sql
     * @param array $params
     * @return array
     */
    public function query($sql, $params = array())
    {
        $conn = $this->getConnection();
        if (empty($params)) {
            /** @var $stmt PDOStatement */
            $stmt = $conn->query($sql);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        $result = $stmt->fetchAll();
        if (is_array($result) && count($result) == 0)
            $result = null;
        return $result;
    }

    /**
     * Just get a record by Id, from some table
     * @param $tableName
     * @param $id
     * @return mixed
     */
    private function getById($tableName, $id)
    {
        $sql = "SELECT * FROM $tableName WHERE id = :id";
        $params = array(
            ':id' => $id
        );
        $results = $this->query($sql, $params);
        if ($results)
            return $results[0];
    }

    /**
     * Just get a record by it Alias, from some table
     * @param $tableName
     * @param $id
     * @return mixed
     */
    private function getByAlias($tableName, $alias)
    {
        $sql = "SELECT * FROM $tableName WHERE alias = :alias";
        $params = array(
            ':alias' => $alias
        );
        $results = $this->query($sql, $params);
        if ($results)
            return $results[0];
    }

//    public function toArray($query, $params = false)
//    {
//        $a = array();
//        $q = $this->query($query, $params);
//        while ($r = $q->fetch(PDO::FETCH_ASSOC)) $a[] = $r;
//        return $a;
//    }

    private function toArrayObject(Array $dataArray, $targetClass, $keyField = null)
    {
        $return = array();
        foreach ($dataArray as $data) {
            $reflection = new ReflectionClass("TiagoGouvea\\PHPGamification\\Model\\" . $targetClass);
            $objInstance = $reflection->newInstanceArgs(array('StdClass' => $data));
            if ($keyField != null)
                $return[$objInstance->get($keyField)] = $objInstance;
            else
                $return[] = $objInstance;
        }
        return $return;
    }

    /**
     * Interface methods
     */


    /**
     * Save a new badge on database table "_badges
     * @param $alias
     * @param $title
     * @param $description
     * @param $imageURL
     * @return Badge
     * @throws Exception
     */
    public function saveBadge($alias, $title, $description, $imageURL = null)
    {
        // Already exists?
        if ($this->getByAlias('gm_badges', $alias))
            throw new Exception(__METHOD__ . ': Alias ' . $alias . ' already exists');

        $sql = 'INSERT INTO gm_badges
                (alias,title, description,image_url)
                VALUES
                (:alias, :title, :description,:image_url)';
        $params = array(
            ':alias' => $alias,
            ':title' => $title,
            ':description' => $description,
            ':image_url' => $imageURL
        );

        $this->execute($sql, $params);
        return $this->getBadgeById($this->lastInsertId());
    }

    /**
     * Get a Badge by Id
     * @param $id
     * @return StdClass
     */
    public function getBadgeById($id)
    {
        return new Badge($this->getById('gm_badges', $id));
    }

    /**
     * Get a Badge by Alias
     * @param $alias
     * @internal param $id
     * @return Badge
     */
    public function getBadgeByAlias($alias)
    {
        $badgeStdClass = $this->getByAlias('gm_badges', $alias);
        return new Badge($badgeStdClass);
    }

    /**
     * Save a new level on database table "_levels"
     * @param $alias
     * @param $title
     * @param $description
     * @param $imageURL
     * @return array
     */
    public function saveLevel($points, $title, $description)
    {
        $sql = 'INSERT INTO gm_levels
                (points, title, description)
                VALUES
                (:points, :title, :description)';
        $params = array(
            ':points' => $points,
            ':title' => $title,
            ':description' => $description
        );

        $this->execute($sql, $params);
        return $this->getLevelById($this->lastInsertId());
    }

    /**
     * Get a Event by Id
     * @param $id
     * @return PHPGamificationLevel
     */
    private function getLevelById($id)
    {
        return $this->getById('gm_levels', $id);
    }

    public function getFirstLevel()
    {
        $sql = 'SELECT * FROM gm_levels ORDER BY points ASC LIMIT 1';
        $results = $this->query($sql);
        return new Level($results[0]);
    }

    public function getNextLevel($levelId, $score)
    {
        $sql = 'SELECT * FROM gm_levels WHERE id<>' . $levelId . ' AND points>' . $score . ' ORDER BY points ASC LIMIT 1';
//        die($sql);
        $results = $this->query($sql);
        if ($results)
            return new Level($results[0]);
    }

    /**
     * Save a new event on database table "_events"
     * @param Event $event
     * @return Event
     */
    public function saveEvent(Event $event)
    {
        $sql = 'INSERT INTO gm_events
                (alias, description, allow_repetitions, reach_required_repetitions, id_each_badge, id_reach_badge, each_points, reach_points, max_points, each_callback, reach_callback, combinable)
                VALUES
                (:alias, :description, :allow_repetitions, :reach_required_repetitions, :id_each_badge, :id_reach_badge, :each_points, :reach_points, :max_points, :each_callback, :reach_callback, :combinable)';
        $params = array(
            ':alias' => $event->getAlias(),
            ':description' => $event->getDescription(),
            ':allow_repetitions' => $event->getAllowRepetitions() ? "1" : "0",
            ':reach_required_repetitions' => $event->getRequiredRepetitions(),
            ':id_each_badge' => $event->getIdEachBadge(),
            ':id_reach_badge' => $event->getIdReachBadge(),
            ':each_points' => $event->getEachPoints(),
            ':reach_points' => $event->getReachPoints(),
            ':max_points' => $event->getMaxPoints(),
            ':each_callback' => $event->getEachCallback(),
            ':reach_callback' => $event->getReachCallback(),
        	':combinable' => $event->getCombinable()
        );

        $this->execute($sql, $params);
        return $this->getEventById($this->lastInsertId());
    }

    /**
     * Get a Event by Id
     * @param $id
     * @return Event
     */
    private function getEventById($id)
    {
        $eventStdClass = $this->getById('gm_events', $id);
        return new Event($eventStdClass);
    }


    public function getLevels()
    {
        $sql = 'SELECT * FROM gm_levels ';
        $result = $this->query($sql);
        if ($result)
            return $this->toArrayObject($result, 'Level', 'id');
    }

    public function getBadges()
    {
        $sql = 'SELECT * FROM gm_badges ';
        $result = $this->query($sql);
        if ($result)
            return $this->toArrayObject($result, 'Badge', 'id');
    }

    public function getEvents()
    {
        $sql = 'SELECT * FROM gm_events ';
        $result = $this->query($sql);
        if ($result)
            return $this->toArrayObject($result, 'Event', 'alias');
    }

    public function getUserAlerts($userId, $resetAlerts = false)
    {
        $sql = 'SELECT id_user, id_badge, id_level FROM gm_user_alerts WHERE id_user = :uid';
        $params = array(
            ':uid' => $userId
        );
        $result = $this->query($sql, $params);
        if ($result && $resetAlerts) {
            $sql = 'DELETE FROM gm_user_alerts WHERE id_user = :uid';
            $params = array(
                ':uid' => $userId
            );
            $this->execute($sql, $params);
        }

        if ($result)
            return $this->toArrayObject($result, 'UserAlert');
    }

    public function getUserBadges($userId)
    {
        $sql = 'SELECT * FROM gm_user_badges WHERE id_user = :uid';
        $params = array(
            ':uid' => $userId
        );
        $result = $this->query($sql, $params);

        if ($result)
            return $this->toArrayObject($result, 'UserBadge');
    }

    public function getUserEvents($userId)
    {
        $sql = 'SELECT * FROM gm_user_events WHERE id_user = :uid ';
        $params = array(
            ':uid' => $userId
        );
        $result = $this->query($sql, $params);
        if ($result)
            return $this->toArrayObject($result, 'UserEvent');
    }

    public function getUserLog($userId)
    {
        $sql = 'SELECT * FROM gm_user_logs WHERE id_user = :uid ORDER BY event_date DESC';
        $params = array(
            ':uid' => $userId
        );
        $result = $this->query($sql, $params);
        if ($result)
            return $this->toArrayObject($result, 'UserLog');
    }

    public function getUserEvent($userId, $eventId)
    {
        $sql = 'SELECT * FROM gm_user_events WHERE id_user = :uid AND id_event = :eid LIMIT 1';
        $params = array(
            ':uid' => $userId,
            ':eid' => $eventId
        );
        $result = $this->query($sql, $params);
        if ($result)
            return new UserEvent($result[0]);
        else {
            $score = new UserEvent();
            return $score;
        }
    }

    /**
     * @param $userId
     * @return UserScore
     */
    public function getUserScore($userId)
    {
        $sql = 'SELECT *
                FROM gm_user_scores
                WHERE id_user = :uid';
        $params = array(
            ':uid' => $userId
        );
        $result = $this->query($sql, $params);
//        echo "GetUserScore:<br>";var_dump($result);
//        echo "<br><br>";
//        die();
        if ($result)
            return new UserScore($result[0]);
        else {
            $score = new UserScore();
            $score->setIdUser($userId);
            $score->setIdLevel($this->getFirstLevel()->getId());
            return $score;
        }
    }

    /**
     * Return users ordered by level
     */
    public function getUsersPointsRanking($limit)
    {
        $sql = 'SELECT *
                FROM gm_user_scores
                ORDER BY points DESC, id_user ASC
                LIMIT ' . $limit;
        $result = $this->query($sql);
        if ($result)
            return $this->toArrayObject($result, 'UserScore');
    }

    public function grantBadgeToUser($userId, $badgeId, $grantDate = "")
    {
        $sql = 'INSERT INTO gm_user_badges (id_user, id_badge, badges_counter, grant_date) VALUES (:uid, :bid, 1, '.($grantDate!="" ? "STR_TO_DATE(:grant_date,'%Y-%m-%d %H:%i:%s')" : "NOW()").') ON DUPLICATE KEY UPDATE badges_counter = badges_counter + 1';
        $params = array(
            ':uid' => $userId,
            ':bid' => $badgeId
        );
        if($grantDate!=""){
        	$params["grant_date"] = $grantDate;
        }
        //error_log($sql);
        $this->execute($sql, $params);
        return true;
    }

    public function hasBadgeUser($userId, $badgeId)
    {
        $sql = 'SELECT coalesce(count(*),0) AS count
                FROM gm_user_badges
                WHERE id_user=:uid AND id_badge=:bid';
        $params = array(
            ':uid' => $userId,
            ':bid' => $badgeId
        );
        $r = $this->query($sql, $params);
        return $r[0]->count > 0;
    }

    public function grantLevelToUser($userId, $levelId)
    {
        $sql = 'UPDATE gm_user_scores
                SET id_level = :lid
                WHERE id_user = :uid';
        $params = array(
            ':uid' => $userId,
            ':lid' => $levelId
        );
        if ($levelId == 0) die ("00");
        return $this->execute($sql, $params);
    }

    public function grantPointsToUser($userId, $points)
    {
        $sql = 'INSERT INTO gm_user_scores
                (id_user, points, id_level)
                VALUES
                (:uid, :p, :firstlevel)
                ON DUPLICATE KEY UPDATE points = points + :p';
        $params = array(
            ':uid' => $userId,
            ':p' => $points,
            ':firstlevel' => 1
        );
        return $this->execute($sql, $params);
    }

    public function logUserEvent($userId, $eventId, $points = null, $badgeId = null, $levelId = null, $eventDate = null)
    {
        $sql = 'INSERT INTO gm_user_logs
                (id_user, id_event, event_date, points, id_badge, id_level)
                VALUES
                (:uid, :eid, :edate, :p, :bid, :lid)';
        $params = array(
            ':uid' => $userId,
            ':eid' => $eventId,
            ':p' => $points,
            ':bid' => $badgeId,
            ':lid' => $levelId,
            ':edate' => ($eventDate ? $eventDate : date("Y-m-d H:i:s",time()))
        );
        return $this->execute($sql, $params);
    }

    /**
     * Insert a user event on "_user_events" database
     * @param $userId
     * @param $eventId
     * @return bool
     */
    public function increaseEventCounter($userId, $eventId)
    {
        $sql = 'INSERT INTO gm_user_events
                (id_user, id_event, event_counter)
                VALUES
                (:uid, :eid, 1)
                ON DUPLICATE KEY UPDATE event_counter = event_counter + 1';
        $params = array(
            ':uid' => $userId,
            ':eid' => $eventId
        );
        return $this->execute($sql, $params);
    }

    public function increaseEventPoints($userId, $eventId, $points)
    {
        $sql = 'UPDATE gm_user_events
                SET points_counter = points_counter + :c
                WHERE id_user = :uid AND id_event = :eid';
        $params = array(
            ':c' => $points,
            ':uid' => $userId,
            ':eid' => $eventId
        );
        return $this->execute($sql, $params);
    }


    public function saveBadgeAlert($userId, $badgeId)
    {
        // echo "User: $userId - badge: $badgeId<br>";
        $sql = 'INSERT INTO gm_user_alerts
                (id_user, id_badge, id_level)
                VALUES
                (:uid, :bid, NULL)';
        $params = array(
            ':uid' => $userId,
            ':bid' => $badgeId
        );
        $this->execute($sql, $params);
        return true;
    }

    public function saveLevelAlert($userId, $levelId)
    {
        $sql = 'INSERT INTO gm_user_alerts
                (id_user, id_badge, id_level)
                VALUES
                (:uid, NULL, :lid)';
        $params = array(
            ':uid' => $userId,
            ':lid' => $levelId
        );
        return $this->execute($sql, $params);
    }

    /**
     * Truncate all tables
     * @param Bool $truncateLevelBadge Truncate the "levels" and "badges" tables (rules)
     * @return bool
     */
    public function truncateDatabase($truncateLevelBadge = false)
    {
        $sql = 'TRUNCATE gm_user_alerts;
                TRUNCATE gm_user_badges;
                TRUNCATE gm_user_events;
                TRUNCATE gm_user_logs;
                TRUNCATE gm_user_scores;';

        if ($truncateLevelBadge)
            $sql .= 'TRUNCATE gm_levels;
                     TRUNCATE gm_badges;
                     TRUNCATE gm_events;';

        $this->execute($sql);
        return true;
    }
    
    /**
     * Sets event_counter
     * @param $event, $eventCounter $idUser
     * @return bool
     */
    public function setEventCounter($idEvent, $idUser, $eventCounter)
    {
    	$sql = 'UPDATE gm_user_events SET event_counter = :eventCounter WHERE id_user = :id_user AND id_event = :id_event';
    	$params = array(
    			':eventCounter' => $eventCounter,
    			':id_user' => $idUser,
    			':id_event' => $idEvent
    	);
    	return $this->execute($sql, $params);
    }


}
