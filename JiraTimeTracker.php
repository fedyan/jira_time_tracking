<?php
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Configuration\ArrayConfiguration;
use Dotenv\Dotenv;
use \JiraRestApi\Issue\Worklog;
use JiraRestApi\JiraException;


class JiraTimeTracker
{
    const DAYS_CHECK = 7;

    const MIN_WORK_HOURS_COUNT = 8;
    /**
     * @var IssueService
     */
    private $targetJiraService;

    /**
     * @var IssueService
     */
    private $sourceJiraService;

    /**
     * @var mixed
     */
    private $sourceUser;

    /**
     * @var mixed
     */
    private $targetUser;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var mixed
     */
    private $targetIssueCode;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $this->sourceJiraService = new IssueService(); //its take connection params from JIRA_* values in .env file
        $this->targetJiraService = new IssueService(new ArrayConfiguration(
            array(
                'jiraHost' => $_ENV['JIRA2_HOST'],
                'jiraUser' => $_ENV['JIRA2_USER'],
                'jiraPassword' => $_ENV['JIRA2_PASS'],
            )
        ));

        $this->sourceUser = $_ENV['JIRA_USER'];
        $this->targetUser = $_ENV['JIRA2_USER'];
        $this->targetIssueCode = $_ENV['JIRA2_TARGET_ISSUE_CODE'];
    }

    /**
     * @throws JiraException
     * @throws JsonMapper_Exception
     */
    public function check()
    {
        for ($i = self::DAYS_CHECK; $i >= 0 ; $i--) {
            $date = new DateTime("-$i day");
            $isWeekend = $date->format('N') >= 6;
            if (!$isWeekend) {
                $targetLoggedTime = $this->getLoggedTime($date, $this->targetJiraService, $this->targetUser);

                if ($targetLoggedTime->getHours() >= self::MIN_WORK_HOURS_COUNT) {
                    printf("[%s] already logged - %s\n", $date->format('d-m-Y'), $targetLoggedTime->getHours());

                    continue;
                }

                $sourceLoggedTime = $this->getLoggedTime($date, $this->sourceJiraService, $this->sourceUser);

                printf("[%s] %s - %s\n", $date->format('d-m-Y'), $sourceLoggedTime->getHours(), $targetLoggedTime->getHours());

                if ($sourceLoggedTime->getHours() >= self::MIN_WORK_HOURS_COUNT) {
                    $this->logTime($sourceLoggedTime);
                    echo 'Time logged!'.PHP_EOL;
                }
            }
        }
    }

    /**
     * @param DateTime $date
     * @throws JsonMapper_Exception
     */
    public function logDay(DateTime $date)
    {
        $sourceLoggedTime = $this->getLoggedTime($date, $this->sourceJiraService, $this->sourceUser);
        $targetLoggedTime = $this->getLoggedTime($date, $this->targetJiraService, $this->targetUser);

        if ($targetLoggedTime->getHours() >= self::MIN_WORK_HOURS_COUNT) {
            printf("[%s] already logged - %s\n", $date->format('d-m-Y'), $targetLoggedTime->getHours());

            return;
        }
        printf("[%s] %s - %s\n", $date->format('d-m-Y'), $sourceLoggedTime->getHours(), $targetLoggedTime->getHours());

        if ($sourceLoggedTime->getHours() >= self::MIN_WORK_HOURS_COUNT) {
            $this->logTime($sourceLoggedTime);
            echo 'Time logged!'.PHP_EOL;
        } else {
            echo 'Not enough time for tracking'.PHP_EOL;
        }
    }

    /**
     * @param DayLoggedTimeDTO $dayLoggedTime
     * @throws JiraException
     * @throws JsonMapper_Exception
     */
    private function logTime(DayLoggedTimeDTO $dayLoggedTime)
    {
        $workLog = new Worklog();
        $workLog->setComment($dayLoggedTime->getComment())
            ->setStarted($dayLoggedTime->getDateTime()->format('Y-m-d 10:10:10'))
            ->setTimeSpent($dayLoggedTime->getHours().'h');

        $this->targetJiraService->addWorklog($this->targetIssueCode, $workLog);

    }

    /**
     * @param DateTime     $date
     * @param IssueService $jiraService
     * @return DayLoggedTimeDTO
     * @throws JsonMapper_Exception
     * @throws JiraException
     */
    private function getLoggedTime(DateTime $date, IssueService $jiraService, string $user)
    {
        //worklogAuthor = currentUser() AND worklogDate = "2015/07/10"
        $jql = sprintf('worklogAuthor = currentUser() AND worklogDate = "%s"', $date->format("Y/m/d"));

        $tasks = $jiraService->search($jql, 0, 15, ['key', 'summary']);

        $worklogComment = '';
        $timeSpent = 0;
        foreach ($tasks->issues as $issue) {

            $worklogs = $this->getWorklogsWithCache($jiraService, $issue->key);

            $issueWorklogTime = $this->calculateWorklog($worklogs, $user, $date);
            $timeSpent += $issueWorklogTime;

            $worklogComment .= sprintf("%s %s %s \n",
                $issue->key,
                $issue->fields->summary,
                $issueWorklogTime
            );
        }

        return (new DayLoggedTimeDTO())->setHours($timeSpent)->setComment($worklogComment)->setDateTime($date);
    }

    /**
     * @param IssueService $jiraService
     * @param string       $issueKey
     */
    private function getWorklogsWithCache(IssueService $jiraService, string $issueKey)
    {
        if (!array_key_exists($issueKey, $this->cache)) {
            $this->cache[$issueKey] = $jiraService->getWorklog($issueKey)->getWorklogs();
        }

        return $this->cache[$issueKey];
    }

    /**
     * @param $worklogs
     * @param $userName
     * @return mixed|null
     */
    private function calculateWorklog($worklogs, $userName, DateTime $date)
    {
        return array_reduce($worklogs, function ( $carry , $worklog ) use ($userName, $date){

            $worklogDate = explode('T', $worklog->started)[0];

            if ( strtolower($worklog->author['name']) === strtolower($userName)
                && $worklogDate === $date->format('Y-m-d')
            ) {
                $carry += round($worklog->timeSpentSeconds/3600, 1);
            }

            return $carry;
        });
    }

}