<?php
require './vendor/autoload.php';

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Configuration\ArrayConfiguration;
use Dotenv\Dotenv;
use \JiraRestApi\Issue\Worklog;


$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$date = isset($argv[1]) && validateDate($argv[1]) ? new DateTime($argv[1]): new DateTime();


$fromJiraService = new IssueService(); //its take connection params from JIRA_* values in .env file

$toJiraService = new IssueService(new ArrayConfiguration(
    array(
        'jiraHost' => $_ENV['JIRA2_HOST'],
        'jiraUser' => $_ENV['JIRA2_USER'],
        'jiraPassword' => $_ENV['JIRA2_PASS'],
    )
));

//worklogAuthor = currentUser() AND worklogDate = "2015/07/10"
$jql = sprintf('worklogAuthor = currentUser() AND worklogDate = "%s"', $date->format("Y/m/d"));

try {

    $tasks = $fromJiraService->search($jql, 0, 15, ['key', 'summary', 'worklog']);

    $worklogComment = '';
    $timeSpent = 0;
    foreach ($tasks->issues as $issue) {
        $issueWorklogTime = calculateWorklog($issue->fields->worklog->worklogs, $_ENV['JIRA_USER'], $date);
        $timeSpent += $issueWorklogTime;

        $worklogComment .= sprintf("%s %s %s \n",
            $issue->key,
            $issue->fields->summary,
            $issueWorklogTime
        );
    }


    $workLog = new Worklog();

    $workLog->setComment($worklogComment)
        ->setStarted($date->format('Y-m-d 10:10:10'))
        ->setTimeSpent($timeSpent.'h');

    $ret = $toJiraService->addWorklog($_ENV['JIRA2_TARGET_ISSUE_CODE'], $workLog);

    $workLogid = $ret->{'id'};

    var_dump($ret);


} catch (JiraRestApi\JiraException $e) {
    $this->assertTrue(false, 'something failed : '.$e->getMessage());
}

/*$worklogs = $fromJiraService->getWorklog($issueKey)->getWorklogs();
var_dump($worklogs);*/





/**
 * @param $worklogs
 * @param $userName
 * @return mixed|null
 */
function calculateWorklog($worklogs, $userName, DateTime $date)
{
    return array_reduce($worklogs, function ( $carry , $worklog ) use ($userName, $date){

        $worklogDate = explode('T', $worklog->started)[0];

        if ( strtolower($worklog->author->name) === strtolower($userName)
            && $worklogDate === $date->format('Y-m-d')
        ) {
            $carry += round($worklog->timeSpentSeconds/3600, 1);
        }

        return $carry;
    });
}

/**
 * @param $date
 * @param string $format
 * @return bool
 */
function validateDate($date, $format = 'd.m.Y')
{
    $d = DateTime::createFromFormat($format, $date);

    return $d && $d->format($format) == $date;
}

