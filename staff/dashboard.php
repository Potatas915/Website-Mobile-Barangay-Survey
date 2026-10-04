<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

/* ---------------------------------------------------------
   Firebase has no query language, so every COUNT(*) below is
   computed in PHP over the rows we already load for the page.
   Each collection is fetched exactly once.
--------------------------------------------------------- */
fb_page(function () use (
    &$total_residents, &$active_surveys, &$total_responses, &$draft_surveys,
    &$completed_surveys, &$engaged_residents, &$participation_rate,
    &$residents_last_month, &$responses_last_month, &$active_last_month,
    &$draft_last_month, &$completed_last_month, &$residents_asof_last_month,
    &$engaged_last_month, &$participation_last_month,
    &$trend_residents, &$trend_responses, &$trend_active, &$trend_draft,
    &$trend_completed, &$trend_participation,
    &$residents_monthly, &$residents_series, &$responses_monthly,
    &$responses_series, &$surveys_monthly, &$surveys_series,
    &$completed_monthly, &$participation_series, &$trend_labels,
    &$chart_survey_list, &$chart_survey_id, &$answered_count,
    &$dist_rows, &$donut_heading, &$dist_total
) {

    // CURDATE() in MySQL.
    $today = date("Y-m-d");

    $residents = array_values(fb_rows("residents"));
    $responses = array_values(fb_rows("responses"));
    $surveys   = array_values(fb_rows("surveys"));

    $count_where = function (array $rows, $predicate) {
        $n = 0;
        foreach ($rows as $row) {
            if ($predicate($row)) {
                $n++;
            }
        }
        return $n;
    };

    // COUNT(DISTINCT resident_id) over a set of rows.
    $distinct_residents = function (array $rows) {
        $ids = array();
        foreach ($rows as $row) {
            $id = (string)($row["resident_id"] ?? "");
            if ($id !== "") {
                $ids[$id] = true;
            }
        }
        return count($ids);
    };

    /* ---------------------------------------------------------
       Current snapshot counts
    --------------------------------------------------------- */
    $total_residents = count($residents);
    $active_surveys = $count_where($surveys, function ($s) use ($today) {
        return ($s["status"] ?? "") === "active"
            && strcmp((string)$s["start_date"], $today) <= 0
            && strcmp((string)$s["end_date"], $today) >= 0;
    });
    $total_responses = count($responses);
    $draft_surveys = $count_where($surveys, function ($s) {
        return ($s["status"] ?? "") === "inactive";
    });
    $completed_surveys = $count_where($surveys, function ($s) use ($today) {
        return strcmp((string)$s["end_date"], $today) < 0;
    });
    $engaged_residents = $distinct_residents($responses);
    $participation_rate = $total_residents > 0 ? round(($engaged_residents / $total_residents) * 100, 1) : 0;

    /* ---------------------------------------------------------
       "vs last month" comparisons, all computed from real timestamps
    --------------------------------------------------------- */
    [$lm_start, $lm_end, ] = month_bounds(1);

    $residents_last_month = $count_where($residents, function ($r) use ($lm_end) {
        return strcmp((string)($r["created_at"] ?? ""), $lm_end) <= 0;
    });
    $responses_last_month = $count_where($responses, function ($r) use ($lm_end) {
        return strcmp((string)($r["submitted_at"] ?? ""), $lm_end) <= 0;
    });
    $active_last_month = $count_where($surveys, function ($s) use ($lm_start, $lm_end) {
        return strcmp((string)$s["start_date"], $lm_end) <= 0
            && strcmp((string)$s["end_date"], $lm_start) >= 0;
    });
    $draft_last_month = $count_where($surveys, function ($s) use ($lm_end) {
        return ($s["status"] ?? "") === "inactive"
            && strcmp((string)($s["created_at"] ?? ""), $lm_end) <= 0;
    });
    $completed_last_month = $count_where($surveys, function ($s) use ($lm_end) {
        return strcmp((string)$s["end_date"], $lm_end) <= 0;
    });
    $residents_asof_last_month = $residents_last_month;
    $engaged_last_month = $distinct_residents(array_values(array_filter($responses, function ($r) use ($lm_end) {
        return strcmp((string)($r["submitted_at"] ?? ""), $lm_end) <= 0;
    })));
    $participation_last_month = $residents_asof_last_month > 0 ? round(($engaged_last_month / $residents_asof_last_month) * 100, 1) : 0;

    $trend_residents = pct_change($total_residents, $residents_last_month);
    $trend_responses = pct_change($total_responses, $responses_last_month);
    $trend_active = pct_change($active_surveys, $active_last_month);
    $trend_draft = pct_change($draft_surveys, $draft_last_month);
    $trend_completed = pct_change($completed_surveys, $completed_last_month);
    $trend_participation = pct_change($participation_rate, $participation_last_month);

    /* ---------------------------------------------------------
       6-month sparklines (oldest -> newest, last point = today)
    --------------------------------------------------------- */
    // Replaces the DATE_FORMAT(...)/GROUP BY helper. The caller passes the
    // already-fetched rows plus the name of the timestamp column.
    function monthly_new_counts(array $rows, $date_col, $months = 6) {
        [$window_start, , ] = month_bounds($months - 1);

        $byMonth = [];
        foreach ($rows as $row) {
            $value = (string)($row[$date_col] ?? "");
            if ($value === "" || strcmp($value, $window_start) < 0) {
                continue;
            }
            $ym = substr($value, 0, 7);
            $byMonth[$ym] = ($byMonth[$ym] ?? 0) + 1;
        }

        $labels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            [$s, , $label] = month_bounds($i);
            $labels[] = [substr($s, 0, 7), $label];
        }
        $out = [];
        foreach ($labels as [$ym, $label]) { $out[] = ["ym" => $ym, "label" => $label, "new" => $byMonth[$ym] ?? 0]; }
        return $out;
    }

    function cumulative_series($totalNow, $monthly) {
        $n = count($monthly);
        $series = array_fill(0, $n, 0);
        $series[$n - 1] = $totalNow;
        for ($i = $n - 1; $i > 0; $i--) {
            $series[$i - 1] = $series[$i] - $monthly[$i]["new"];
        }
        return $series;
    }

    $residents_monthly = monthly_new_counts($residents, "created_at");
    $residents_series = cumulative_series($total_residents, $residents_monthly);

    $responses_monthly = monthly_new_counts($responses, "submitted_at");
    $responses_series = cumulative_series($total_responses, $responses_monthly);

    $surveys_monthly = monthly_new_counts($surveys, "created_at");
    $surveys_series = cumulative_series(count($surveys), $surveys_monthly);

    $completed_monthly = [];
    $participation_series = [];
    foreach ($residents_monthly as $i => $m) {
        $end = month_bounds(count($residents_monthly) - 1 - $i)[1];
        $r = $count_where($residents, function ($row) use ($end) {
            return strcmp((string)($row["created_at"] ?? ""), $end) <= 0;
        });
        $e = $distinct_residents(array_values(array_filter($responses, function ($row) use ($end) {
            return strcmp((string)($row["submitted_at"] ?? ""), $end) <= 0;
        })));
        $completed_monthly[] = $count_where($surveys, function ($s) use ($end) {
            return strcmp((string)$s["end_date"], $end) <= 0;
        });
        $participation_series[] = $r > 0 ? round(($e / $r) * 100, 1) : 0;
    }

    /* ---------------------------------------------------------
       Participation trend (monthly participation rate, for the big line chart)
    --------------------------------------------------------- */
    $trend_labels = array_column($residents_monthly, "label");

    /* ---------------------------------------------------------
       Responses by survey (default), or a single survey's own
       participation if one is picked from the dropdown. "All
       Surveys" shows how total responses are split across every
       survey, so the chart stays meaningful as more surveys pile
       up. Picking one specific survey instead shows how many
       residents have answered it vs how many haven't yet.
    --------------------------------------------------------- */
    $chart_survey_list = array();
    foreach ($surveys as $survey) {
        $chart_survey_list[] = array(
            "survey_id"   => $survey["id"],
            "title"       => $survey["title"] ?? "",
            // needed by the newest-first sort below
            "created_at"  => $survey["created_at"] ?? "",
        );
    }
    usort($chart_survey_list, function ($a, $b) {
        return strcmp((string)$b["created_at"], (string)$a["created_at"]);
    });

    $chart_survey_id = (isset($_GET["chart_survey"]) && $_GET["chart_survey"] !== "all") ? (int)$_GET["chart_survey"] : "all";
    $answered_count = 0;

    if ($chart_survey_id === "all") {

        // The GROUP BY s.survey_id, s.title with a HAVING votes > 0.
        $votes_by_survey = [];
        foreach ($responses as $response) {
            $survey_id = (string)($response["survey_id"] ?? "");
            $votes_by_survey[$survey_id] = ($votes_by_survey[$survey_id] ?? 0) + 1;
        }

        $survey_dist_raw = [];
        foreach ($surveys as $survey) {
            $votes = $votes_by_survey[(string)$survey["id"]] ?? 0;
            if ($votes > 0) {
                $survey_dist_raw[] = array("title" => $survey["title"] ?? "", "votes" => $votes);
            }
        }
        usort($survey_dist_raw, function ($a, $b) {
            return $b["votes"] <=> $a["votes"];
        });

        $dist_rows = array_slice($survey_dist_raw, 0, 5);
        if (count($survey_dist_raw) > 5) {
            $others_total = array_sum(array_column(array_slice($survey_dist_raw, 5), "votes"));
            if ($others_total > 0) {
                $dist_rows[] = ["title" => "Other Surveys", "votes" => $others_total];
            }
        }
        $donut_heading = "Responses by Survey";
    } else {

        $sel_survey = null;
        foreach ($surveys as $survey) {
            if ((string)$survey["id"] === (string)$chart_survey_id) {
                $sel_survey = $survey;
                break;
            }
        }

        $answered_count = $distinct_residents(array_values(array_filter($responses, function ($r) use ($chart_survey_id) {
            return (string)($r["survey_id"] ?? "") === (string)$chart_survey_id;
        })));
        $not_answered_count = max($total_residents - $answered_count, 0);

        $dist_rows = [];
        if ($answered_count > 0) $dist_rows[] = ["title" => "Answered", "votes" => $answered_count];
        if ($not_answered_count > 0) $dist_rows[] = ["title" => "Not Yet Answered", "votes" => $not_answered_count];

        $donut_heading = $sel_survey ? $sel_survey["title"] . " - Participation" : "Responses by Survey";
    }

    $dist_total = array_sum(array_column($dist_rows, "votes"));
});

$dist_colors = ["#147a63", "#1fae82", "#58c9a0", "#f5a623", "#3b6fed", "#6b7d78"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . "/../assets/css/style.css") ?>">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/staff_nav.php"; ?>
<div class="container">
    <?php include __DIR__ . "/../includes/staff_topbar.php"; ?>

    <div class="welcome-header">
        <div>
            <h1>Welcome back, <?= e($_SESSION["staff_name"] ?? "Health Administrator") ?> <span class="wave">👋</span></h1>
            <p>Monitor community participation and health survey insights.</p>
        </div>
    </div>

    <!-- Primary KPIs: the 3 numbers that matter most at a glance -->
    <div class="stat-cards-grid-primary">
        <div class="stat-card stat-card-primary">
            <div class="stat-card-top">
                <span class="stat-ic teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="stat-label">Total Residents</span>
            </div>
            <div class="stat-number"><?= number_format($total_residents) ?></div>
            <div class="stat-trend-row">
                <span class="trend <?= $trend_residents["up"] ? "up" : "down" ?>"><?= $trend_residents["up"] ? "↑" : "↓" ?> <?= $trend_residents["pct"] ?>%</span>
                <small>vs last month</small>
                <?= sparkline_svg($residents_series, 70, 26, $trend_residents["up"] ? "#1fae82" : "#ef4a63") ?>
            </div>
        </div>

        <div class="stat-card stat-card-primary">
            <div class="stat-card-top">
                <span class="stat-ic teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                <span class="stat-label">Active Surveys</span>
            </div>
            <div class="stat-number"><?= number_format($active_surveys) ?></div>
            <div class="stat-trend-row">
                <span class="trend <?= $trend_active["up"] ? "up" : "down" ?>"><?= $trend_active["up"] ? "↑" : "↓" ?> <?= $trend_active["pct"] ?>%</span>
                <small>vs last month</small>
                <?= sparkline_svg($surveys_series, 70, 26, $trend_active["up"] ? "#1fae82" : "#ef4a63") ?>
            </div>
        </div>

        <div class="stat-card stat-card-primary">
            <div class="stat-card-top">
                <span class="stat-ic amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <span class="stat-label">Participation Rate</span>
            </div>
            <div class="stat-number"><?= $participation_rate ?>%</div>
            <div class="stat-trend-row">
                <span class="trend <?= $trend_participation["up"] ? "up" : "down" ?>"><?= $trend_participation["up"] ? "↑" : "↓" ?> <?= $trend_participation["pct"] ?>%</span>
                <small>vs last month</small>
                <?= sparkline_svg($participation_series, 70, 26, $trend_participation["up"] ? "#1fae82" : "#ef4a63") ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions sits right under the headline stats, above the fold,
         instead of below all the charts. -->
    <div class="card">
        <h2>Quick Actions</h2>
        <div class="quick-actions-grid">
            <a class="quick-action-card" href="survey_management.php">
                <span class="quick-action-icon teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h4"/><path d="M9 4h11a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H9"/><line x1="13" y1="9" x2="18" y2="9"/><line x1="13" y1="13" x2="18" y2="13"/><line x1="13" y1="17" x2="16" y2="17"/></svg>
                </span>
                <span class="quick-action-text">
                    <strong>Manage Surveys</strong>
                    <small>View, edit, and organize all surveys</small>
                </span>
                <span class="quick-action-arrow">&#8594;</span>
            </a>
            <a class="quick-action-card" href="survey_add.php">
                <span class="quick-action-icon amber">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </span>
                <span class="quick-action-text">
                    <strong>Create New Survey</strong>
                    <small>Launch a new resident survey</small>
                </span>
                <span class="quick-action-arrow">&#8594;</span>
            </a>
            <a class="quick-action-card" href="results.php">
                <span class="quick-action-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </span>
                <span class="quick-action-text">
                    <strong>View Results</strong>
                    <small>See responses and insights</small>
                </span>
                <span class="quick-action-arrow">&#8594;</span>
            </a>

            <a class="quick-action-card" href="register.php">
                <span class="quick-action-icon teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </span>
                <span class="quick-action-text">
                    <strong>Register New Resident</strong>
                    <small>Add a resident to the system</small>
                </span>
                <span class="quick-action-arrow">&#8594;</span>
            </a>

            <a class="quick-action-card" href="reports.php?print=1">
                <span class="quick-action-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </span>
                <span class="quick-action-text">
                    <strong>Print Reports</strong>
                    <small>Open the print dialog instantly</small>
                </span>
                <span class="quick-action-arrow">&#8594;</span>
            </a>
        </div>
    </div>

    <!-- Secondary stats: supporting detail, deliberately smaller and quieter
         than the primary row above so they don't compete for attention. -->
    <div class="stat-cards-grid-secondary">
        <div class="stat-card stat-card-secondary">
            <div class="stat-card-top">
                <span class="stat-ic teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                <span class="stat-label">Total Responses</span>
            </div>
            <div class="stat-number"><?= number_format($total_responses) ?></div>
            <div class="stat-trend-row">
                <span class="trend <?= $trend_responses["up"] ? "up" : "down" ?>"><?= $trend_responses["up"] ? "↑" : "↓" ?> <?= $trend_responses["pct"] ?>%</span>
                <small>vs last month</small>
                <?= sparkline_svg($responses_series, 70, 26, $trend_responses["up"] ? "#1fae82" : "#ef4a63") ?>
            </div>
        </div>

        <div class="stat-card stat-card-secondary">
            <div class="stat-card-top">
                <span class="stat-ic amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                <span class="stat-label">Draft Surveys</span>
            </div>
            <div class="stat-number"><?= number_format($draft_surveys) ?></div>
            <div class="stat-trend-row">
                <span class="trend <?= $trend_draft["up"] ? "up" : "down" ?>"><?= $trend_draft["up"] ? "↑" : "↓" ?> <?= $trend_draft["pct"] ?>%</span>
                <small>vs last month</small>
                <?= sparkline_svg(array_map(fn($v) => max($v, 0.01), $residents_series), 70, 26, "#f5a623") ?>
            </div>
        </div>

        <div class="stat-card stat-card-secondary">
            <div class="stat-card-top">
                <span class="stat-ic green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                <span class="stat-label">Completed Surveys</span>
            </div>
            <div class="stat-number"><?= number_format($completed_surveys) ?></div>
            <div class="stat-trend-row">
                <span class="trend <?= $trend_completed["up"] ? "up" : "down" ?>"><?= $trend_completed["up"] ? "↑" : "↓" ?> <?= $trend_completed["pct"] ?>%</span>
                <small>vs last month</small>
                <?= sparkline_svg($completed_monthly, 70, 26, "#1fae82") ?>
            </div>
        </div>
    </div>

    <div class="dashboard-charts-row">
        <div class="card chart-card">
            <div class="chart-card-head">
                <h2>Participation Trend</h2>
                <span class="chart-range-label">Last 6 Months</span>
            </div>
            <?php if ($total_responses === 0): ?>
                <p class="muted-note">No survey responses yet — this chart fills in once residents start answering surveys.</p>
            <?php else: ?>
            <?php
                $w = 720; $h = 220; $padL = 34; $padR = 5; $padB = 22; $padT = 10;
                $plotW = $w - $padL - $padR; $plotH = $h - $padB - $padT;
                $n = count($participation_series);
                $maxV = 100;
                $pts = [];
                foreach ($participation_series as $i => $v) {
                    $x = $padL + ($n > 1 ? ($i / ($n - 1)) * $plotW : 0);
                    $y = $padT + $plotH - ($v / $maxV) * $plotH;
                    $pts[] = [$x, $y, $v];
                }
                $lineStr = implode(" ", array_map(fn($p) => round($p[0],1).",".round($p[1],1), $pts));
                $areaStr = $lineStr . " " . round($pts[$n-1][0],1) . "," . ($padT+$plotH) . " " . round($pts[0][0],1) . "," . ($padT+$plotH);
            ?>
            <svg viewBox="0 0 <?= $w ?> <?= $h ?>" class="trend-chart" preserveAspectRatio="none">
                <?php foreach ([0,25,50,75,100] as $g): $gy = $padT + $plotH - ($g/$maxV)*$plotH; ?>
                    <line x1="<?= $padL ?>" y1="<?= $gy ?>" x2="<?= $w ?>" y2="<?= $gy ?>" stroke="#eef1f8" stroke-width="1"/>
                    <text x="0" y="<?= $gy + 4 ?>" class="chart-axis-label"><?= $g ?>%</text>
                <?php endforeach; ?>
                <polygon points="<?= $areaStr ?>" fill="url(#trendFill)" opacity="0.5"/>
                <polyline points="<?= $lineStr ?>" fill="none" stroke="#147a63" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                <?php foreach ($pts as $p): ?>
                    <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4" fill="#fff" stroke="#147a63" stroke-width="2"/>
                <?php endforeach; ?>
                <defs>
                    <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#1fae82" stop-opacity="0.35"/>
                        <stop offset="100%" stop-color="#1fae82" stop-opacity="0"/>
                    </linearGradient>
                </defs>
            </svg>
            <div class="chart-x-labels" style="padding-left:<?= $padL ?>px;">
                <?php foreach ($trend_labels as $lbl): ?><span><?= e($lbl) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card donut-card">
            <div class="chart-card-head">
                <h2 title="<?= e($donut_heading) ?>"><?= e($donut_heading) ?></h2>
                <form method="GET" style="margin:0;">
                    <select name="chart_survey" class="donut-select" onchange="this.form.submit()">
                        <option value="all" <?= $chart_survey_id === "all" ? "selected" : "" ?>>All Surveys</option>
                        <?php foreach ($chart_survey_list as $s): ?>
                            <option value="<?= $s["survey_id"] ?>" <?= (string)$chart_survey_id === (string)$s["survey_id"] ? "selected" : "" ?>><?= e($s["title"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php if ($dist_total === 0): ?>
                <p class="muted-note"><?= $chart_survey_id === "all" ? "No survey responses yet — this chart fills in once residents start answering surveys." : "No one has answered this survey yet." ?></p>
            <?php else: ?>
                <?php
                    $gradientParts = [];
                    $running = 0;
                    foreach ($dist_rows as $i => $row) {
                        $slice = ($row["votes"] / $dist_total) * 360;
                        $color = $dist_colors[$i % count($dist_colors)];
                        $gradientParts[] = "$color " . round($running, 2) . "deg " . round($running + $slice, 2) . "deg";
                        $running += $slice;
                    }
                    $gradientCss = implode(", ", $gradientParts);
                ?>
                <div class="donut-wrap">
                    <div class="donut" style="background: conic-gradient(<?= $gradientCss ?>);">
                        <div class="donut-hole">
                            <strong><?= $chart_survey_id === "all" ? number_format($dist_total) : number_format($answered_count) ?></strong>
                            <small>Responses</small>
                        </div>
                    </div>
                </div>
                <div class="donut-legend">
                    <?php foreach ($dist_rows as $i => $row):
                        $pct = round(($row["votes"] / $dist_total) * 100, 1);
                        $label = $row["title"];
                        if (strlen($label) > 26) $label = substr($label, 0, 25) . "...";
                    ?>
                        <div class="donut-legend-row">
                            <span class="dot" style="background:<?= $dist_colors[$i % count($dist_colors)] ?>;"></span>
                            <span class="legend-label" title="<?= e($row["title"]) ?>"><?= e($label) ?></span>
                            <span class="legend-value"><?= number_format($row["votes"]) ?> (<?= $pct ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                    <div class="donut-legend-total"><span>Total</span><span><?= number_format($dist_total) ?></span></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>