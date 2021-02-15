<?php
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__);

require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'/JiraTimeTracker.php';
require $_SERVER['DOCUMENT_ROOT'].'/DayLoggedTimeDTO.php';

echo "VPN connection must be enabled!";


$jiraTimeTracker = new JiraTimeTracker();

try {
    if (isset($argv[1]) && validateDate($argv[1])) {
        $jiraTimeTracker->logDay(new DateTime($argv[1]));
    } else {
        $jiraTimeTracker->check();
    }

    $output = shell_exec('/usr/bin/notify-send --urgency=normal --expire-time=20000 -h int:x:500 -h int:y:500 "Скрипт трекинга времени отработал"');

} catch (Throwable $e) {
    echo 'Возникла ошибка:'.$e->getMessage();
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