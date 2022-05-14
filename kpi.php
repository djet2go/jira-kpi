<head>
    <link href="app.css" rel="stylesheet" type="text/css">
    <title>Jira - KPI reporter</title>
    <link rel="icon" href="kpi3.png" />
</head>

<body>
    <div id="preview" class="preview-html" preview="" debounce="150">

        <?php
        require_once dirname(__FILE__) . '/jiraUnirest.php';

        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);

        $useQuery = $ini_array["useQuery"];
        $startTm = microtime(true);
        $getParam = $_GET;

        $meetStat = parse_ini_file($ini_array["path"]["result"]."stats.ini");
        if (empty($meetStat)) {
            $meetStat = [];
        }

        if (isset($getParam["emp"])) {
            $workDays = $getParam["emp"];
        } else {
            $workDays = $ini_array["workDays"];
        }

        function requestConfigurator($getParam, $ini_array, $request)
        {
            $useQuery = $ini_array["useQuery"];
            if (isset($getParam["jql"])) {
                $request = $getParam["jql"];
            } elseif (isset($getParam["dateFrom"]) and isset($getParam["dateTo"])) {
                $dateFrom = date('Y-m-d', strtotime($getParam["dateFrom"]));
                $dateTo = date('Y-m-d', strtotime($getParam["dateTo"]));
                $request = sprintf($ini_array["query"][$useQuery], $dateFrom, $dateTo);
            } else {
                $request = $ini_array["query"]["default_kpi"];
            }
            return $request;
        }


        function getTable($employerStatistic, $workDays, $tables)
        {
            $tableSum = [];
            $table = "<table border=0 class=\"table-fixed-head\">
            <thead><tr><th><b>Ключ</b></th><th><b>Название&nbsp;постав.&nbsp;задачи</b></th><th><b>Сложность</b>, storypoints</th><th><b>Оценка времени</b>, ч/ч</th><th><b>Потрачено времени</b>, ч/ч</th><th><b>Эффективность</b>, %</th></tr></thead>";

            foreach ($employerStatistic as $emp => $obj) {
                // Emploer summary
                $tsAll = 0;
                $eAll = 0;
                $tbStr = "";

                $tsAll = $obj["summary"]["timespent"] + $obj["summary"]["timespentBug"] - $obj["summary"]["timespentSomeoneElsesBug"] - $obj["summary"]["reviewTimespent"];
                // $obg["summary"]["meetingSpent"]
                // $tsAll = $obj["summary"]["timespent"] + $obj["summary"]["timespentBug"] - $obj["summary"]["timespentSomeoneElsesBug"];
                if ($obj["summary"]["estimate"] == 0) {
                    $eAll = 0;
                }else {
                    $eAll = round($obj["summary"]["estimate"] / ($tsAll) * 100, 2);
                }

                $embStr = "";

                if (isset($obj["summary"]["timespentBug"]) or isset($obj["summary"]["timespentSomeoneElsesBug"]) or (isset($obj["summary"]["reviewTimespent"]) and $obj["summary"]["reviewTimespent"] != 0) or isset($obj["summary"]["meetingSpent"])) {
                    $tbStr = "=Затрекано: " . $obj["summary"]["timespent"] . " h<br>";
                    // $tbStr = "=Отработанное время: " . $obj["summary"]["timespent"] + $obj["summary"]["meetingSpent"]. " h<br>";
                }

                if (isset($obj["summary"]["timespentBug"])) {
                    $embStr .= "(могло бы быть: " . $obj["summary"]["percentEfficiency"] . "%)</br>";
                    $tbStr .= "+" . $obj["summary"]["timespentBug"] . " h на фикс багов другими<br>";
                }

                if (isset($obj["summary"]["timespentSomeoneElsesBug"])) {
                    $tbStr .= "-" . $obj["summary"]["timespentSomeoneElsesBug"] . " h на фикс чужих багов<br>";
                } else {
                    $tbStr .= "";
                }

                if (isset($obj["summary"]["reviewTimespent"]) and $obj["summary"]["reviewTimespent"] != 0) {
                    $tbStr .= "-" . $obj["summary"]["reviewTimespent"] . " h на ревью чужого кода<br>";
                }

                if (isset($obj["summary"]["meetingSpent"]) and $obj["summary"]["meetingSpent"] != 0) {
                    $tbStr .= "+<b>" . $obj["summary"]["meetingSpent"] . "&nbsp;h</b> проведено на митингах<br>";
                }
                
                if ($obj["summary"]["estimate"] == 0) {
                    $compliteEfficiency = round($obj["summary"]["percentEfficiencyUseWorkTime"], 2);
                }else {
                    $compliteEfficiency = round(($eAll * 0.3) + ($obj["summary"]["percentEfficiencyUseWorkTime"] * 0.7), 2);
                }

                $allSpentTime = $obj["summary"]["timespent"] + $obj["summary"]["meetingSpent"];
                $textColor = "black";
                if ($allSpentTime < ($obj["summary"]["needHours"] - round(($obj["summary"]["needHours"]/100)*20, 2))){
                    $textColor = "red";
                }
                if ($tsAll < 0.0001) {
                    $tsAll = 0;
                }
                $tsStr = "Работа над поставленными задачами: <b>" . $tsAll . " h</b></br></br>";
                $tsStr .= "<b>Всего отработано <font color=\"".$textColor."\">" . $allSpentTime . "&nbsp;h</font> (из положенных ".$obj["summary"]["needHours"]. " h): </b></br>";
                
                if ($eAll == 0) {
                    $eStr = "<br>Эффективность выполнения поставленных задач: <b> -- </b></br></br>";
                }else {
                    $eStr = "<br>Эффективность выполнения поставленных задач: <b>" . $eAll . "%</b></br></br>";
                }
                
                $timeStr = $tsStr . $tbStr;
                $efStr = $eStr . $embStr;
                $taskCountPercent = round((($obj["summary"]["countIssues"] - $obj["summary"]["countReviews"]) / $workDays[$emp]) * 100, 2);
                $countDevIssue = $obj["summary"]["countIssues"] - $obj["summary"]["countReviews"];
                if ($compliteEfficiency >= 115) {
                    $table .= "<tr><td style=\"vertical-align: top; padding-top: 5px\"><img src=\"gold-status2.png\" width=\"130\"></td><td id=\"table-fixed-head-summ\"><b>" . $emp . "</b><br>Кол-во задач: <b>" . $obj["summary"]["countIssues"] . "</b> (" . $taskCountPercent . "%)<br>(из них на <b>".$countDevIssue." шт. на разработку</b> и ".$obj["summary"]["countReviews"]." шт. на ревью)<br><br>Сложность : <b>" . $obj["summary"]["storyPoints"] . " storypoints</b><br>Оценка времени: <b>" . $obj["summary"]["estimate"] . " h</b><br>" . $timeStr . $efStr . "Эффективность использования рабочего времени: <b>" . $obj["summary"]["percentEfficiencyUseWorkTime"] . "%</b><br /><br /><b>Итоговая эффективность: " . $compliteEfficiency . "%</b></td><td></td><td></td><td></td><td></td></tr>";
                }elseif ($compliteEfficiency >= 100) {
                    $table .= "<tr><td style=\"vertical-align: top; padding-top: 5px\"><img src=\"gold-status.png\" width=\"130\"></td><td id=\"table-fixed-head-summ\"><b>" . $emp . "</b><br>Кол-во задач: <b>" . $obj["summary"]["countIssues"] . "</b> (" . $taskCountPercent . "%)<br>(из них на <b>".$countDevIssue." шт. на разработку</b> и ".$obj["summary"]["countReviews"]." шт. на ревью)<br><br>Сложность : <b>" . $obj["summary"]["storyPoints"] . " storypoints</b><br>Оценка времени: <b>" . $obj["summary"]["estimate"] . " h</b><br>" . $timeStr . $efStr . "Эффективность использования рабочего времени: <b>" . $obj["summary"]["percentEfficiencyUseWorkTime"] . "%</b><br /><br /><b>Итоговая эффективность: " . $compliteEfficiency . "%</b></td><td></td><td></td><td></td><td></td></tr>";
                }elseif ($compliteEfficiency >= 90 AND $compliteEfficiency < 100) {
                    $table .= "<tr><td style=\"vertical-align: top; padding-top: 5px\"><img src=\"level-up.png\" width=\"130\"></td><td id=\"table-fixed-head-summ\"><b>" . $emp . "</b><br>Кол-во задач: <b>" . $obj["summary"]["countIssues"] . "</b> (" . $taskCountPercent . "%)<br>(из них на <b>".$countDevIssue." шт. на разработку</b> и ".$obj["summary"]["countReviews"]." шт. на ревью)<br><br>Сложность : <b>" . $obj["summary"]["storyPoints"] . " storypoints</b><br>Оценка времени: <b>" . $obj["summary"]["estimate"] . " h</b><br>" . $timeStr . $efStr . "Эффективность использования рабочего времени: <b>" . $obj["summary"]["percentEfficiencyUseWorkTime"] . "%</b><br /><br /><b>Итоговая эффективность: " . $compliteEfficiency . "%</b></td><td></td><td></td><td></td><td></td></tr>";
                }elseif ($compliteEfficiency < 20) {
                    $table .= "<tr><td style=\"vertical-align: top; padding-top: 5px\"><img src=\"game-over.png\" width=\"130\"></td><td id=\"table-fixed-head-summ\"><b>" . $emp . "</b><br>Кол-во задач: <b>" . $obj["summary"]["countIssues"] . "</b> (" . $taskCountPercent . "%)<br>(из них на <b>".$countDevIssue." шт. на разработку</b> и ".$obj["summary"]["countReviews"]." шт. на ревью)<br><br>Сложность : <b>" . $obj["summary"]["storyPoints"] . " storypoints</b><br>Оценка времени: <b>" . $obj["summary"]["estimate"] . " h</b><br>" . $timeStr . $efStr . "Эффективность использования рабочего времени: <b>" . $obj["summary"]["percentEfficiencyUseWorkTime"] . "%</b><br /><br /><b>Итоговая эффективность: " . $compliteEfficiency . "%</b></td><td></td><td></td><td></td><td></td></tr>";
                }else {
                    $table .= "<tr><td></td><td id=\"table-fixed-head-summ\"><b>" . $emp . "</b><br>Кол-во задач: <b>" . $obj["summary"]["countIssues"] . "</b> (" . $taskCountPercent . "%)<br>(из них на <b>".$countDevIssue." шт. на разработку</b> и ".$obj["summary"]["countReviews"]." шт. на ревью)<br><br>Сложность : <b>" . $obj["summary"]["storyPoints"] . " storypoints</b><br>Оценка времени: <b>" . $obj["summary"]["estimate"] . " h</b><br>" . $timeStr . $efStr . "Эффективность использования рабочего времени: <b>" . $obj["summary"]["percentEfficiencyUseWorkTime"] . "%</b><br /><br /><b>Итоговая эффективность: " . $compliteEfficiency . "%</b></td><td></td><td></td><td></td><td></td></tr>";
                }

                $tableSum[$emp] = $compliteEfficiency;
                // Task list
                foreach ($obj["issues"] as $issueKey => $issueValue) {
                    $textColor = "black";
                    if ($issueValue["gitCommit"] == false) {
                        $textColor = "red";
                    }elseif ($issueValue["gitCommit"] == true AND $issueValue["percentEfficiency"] >= 85 AND $issueValue["percentEfficiency"] <= 150) {
                        $textColor = "green";
                    }
                    $table .= "<tr><td><a href=" . $issueValue["link"] . " target=\"_blank\">" . $issueKey . "</a></td><td id=\"table-fixed-head-sum\">" . $issueValue["name"] . "</td><td>" . $issueValue["storyPoints"] . "</td><td>" . $issueValue["estimate"] . "</td><td>" . $issueValue["timespent"] . "</td><td> <font color=\"".$textColor."\">" . $issueValue["percentEfficiency"] . "%</font></td></tr>";
                }
            }
            $table .= "</table>";
            arsort($tableSum, SORT_NUMERIC);
            $tableSumStr = "<table border=1>
            <tr><td><b>Сотрудник</b></td><td><b>Эффективность</b></td></tr>";
            foreach ($tableSum as $key => $value) {
                $tableSumStr .= "<tr><td>" . $key . "</td><td><b>" . $value . "%</b>";
            }
            $tableSumStr .= "</table>";
            $tables = [
                "table" => $table,
                "tableSum" => $tableSumStr,
            ];

            return $tables;
        }
        $request = requestConfigurator($getParam, $ini_array, NULL);
        echo "<b>JQL-запрос: </b><pre>" . $request . "</pre><hr>";
        $jira3 = new Jira;
        $jira3->init($ini_array);
        $jira3->search($request);

        $employerStatistic = [];
        $employerStatistic2 = [];
        $browse = $ini_array["connect"]["host"] . $ini_array["methods"]["browse"] . "/";

        foreach ($jira3->issues as $key => $value) {

            $employer = $value->fields->assignee->displayName;
            $employerEmail = $ini_array["emails"]["$employer"];
            $taskType = $value->fields->issuetype->id;
            $taskName = $value->fields->summary;
            $taskKey = $value->key;
            $timeoriginalestimate = round($value->fields->timeoriginalestimate / 60 / 60, 2);
            $timespent = round($value->fields->timespent / 60 / 60, 2);
            $customSP = $ini_array["customFields"]["storyoints"];
            $storyPoints = $value->fields->$customSP;
            if (!isset($storyPoints)) {
                $storyPoints = 0;
            }

            $customBC = $ini_array["customFields"]["bug_creator"];
            $bugCreator = $value->fields->$customBC->displayName;
            $customGC = $ini_array["customFields"]["gitCommit"];
            if ($value->fields->$customGC == "{}") {
                $gitCommit = false;
            }else {
                $gitCommit = true;
            }
            if (isset($bugCreator) and $bugCreator != $employer and $timespent > 0) {
                if (isset($employerStatistic["$bugCreator"]["summary"]["timespentBug"])) {
                    $employerStatistic["$bugCreator"]["summary"]["timespentBug"] += $timespent;
                    $employerStatistic["$employer"]["summary"]["timespentSomeoneElsesBug"] += $timespent;
                } else {
                    $employerStatistic["$bugCreator"]["summary"]["timespentBug"] = $timespent;
                    $employerStatistic["$employer"]["summary"]["timespentSomeoneElsesBug"] += $timespent;
                }
            }

            $employerStatistic["$employer"]["issues"]["$taskKey"] = [
                "name" => $taskName,
                "link" => $browse . $taskKey,
                "storyPoints" => $storyPoints,
                "estimate" => $timeoriginalestimate,
                "timespent" => $timespent,
                "gitCommit" => $gitCommit,
                "percentEfficiency" => round(($timeoriginalestimate / $timespent) * 100, 2),
            ];


            if ($taskType != $ini_array["issueType"]["Bug"]) {
                $employerStatistic2["$employer"]["estimate"] += $timeoriginalestimate;
            } else {
                $employerStatistic2["$employer"]["estimate"] += 0;
            }
            
            foreach ($value->fields->labels as $keyLabel => $valueLabel) {
                if ($valueLabel == "review") {
                    if (isset($employerStatistic2["$employer"]["reviewTimespent"])) {
                        $employerStatistic2["$employer"]["reviewTimespent"] += $timespent;
                    } else {
                        $employerStatistic2["$employer"]["reviewTimespent"] = $timespent;
                    }
                    $employerStatistic2["$employer"]["countReviews"] += 1;
                }
            }

            $employerStatistic2["$employer"]["timespent"] += $timespent;
            $employerStatistic2["$employer"]["countIssues"] += 1;
            $employerStatistic2["$employer"]["storyPoints"] += $storyPoints;

            $employerStatistic["$employer"]["summary"]["countIssues"] = $employerStatistic2["$employer"]["countIssues"];
            $employerStatistic["$employer"]["summary"]["storyPoints"] = $employerStatistic2["$employer"]["storyPoints"];
            $employerStatistic["$employer"]["summary"]["estimate"] = $employerStatistic2["$employer"]["estimate"];
            $employerStatistic["$employer"]["summary"]["timespent"] = $employerStatistic2["$employer"]["timespent"];
            $employerStatistic["$employer"]["summary"]["percentEfficiency"] = round($employerStatistic2["$employer"]["estimate"] / $employerStatistic2["$employer"]["timespent"] * 100, 2);
            $employerStatistic["$employer"]["summary"]["reviewTimespent"] = round($employerStatistic2["$employer"]["reviewTimespent"], 2);
            $employerStatistic["$employer"]["summary"]["percentEfficiencyUseWorkTime"] = round((($employerStatistic2["$employer"]["timespent"] + $meetStat[$employerEmail]) / ($ini_array["workDays"][$employer] * $ini_array["week"]["dayHours"])) * 100, 2);
            $employerStatistic["$employer"]["summary"]["countReviews"] = $employerStatistic2["$employer"]["countReviews"];
            $employerStatistic["$employer"]["summary"]["meetingSpent"] = $meetStat[$employerEmail];
            $employerStatistic["$employer"]["summary"]["needHours"] = $ini_array["workDays"][$employer] * $ini_array["week"]["dayHours"];
        }

        $tables = getTable($employerStatistic, $workDays, $table);
        echo $tables["table"]."<hr />";
        echo $tables["tableSum"];

        // Отображаем время выполнения скрипта
        $deltaTm = microtime(true) - $startTm;
        echo "\nВремя выполнения: <code>" . round($deltaTm, 2) . " сек.</code>";
