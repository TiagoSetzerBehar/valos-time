@php
    /** VALO's Time: one-page weekly summary (A4 = 794 x 1123 CSS px). */
    $fmt = static function (int $seconds): string {
        $minutes = intdiv($seconds + 30, 60);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m.' min';
        }
        return $m === 0 ? $h.' h' : $h.' h '.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    };
    $delta = static function (int $now, int $before) use ($fmt): array {
        if ($before === 0 && $now === 0) {
            return ['text' => '—', 'class' => 'flat'];
        }
        if ($before === 0) {
            return ['text' => 'nuevo', 'class' => 'up'];
        }
        $pct = (int) round(($now - $before) / $before * 100);
        if ($pct === 0) {
            return ['text' => '= igual', 'class' => 'flat'];
        }
        return ['text' => ($pct > 0 ? '▲ ' : '▼ ').min(abs($pct), 999).($pct > 999 ? '+' : '').' %', 'class' => $pct > 0 ? 'up' : 'down'];
    };
    $total = $summary['total'];
    $previousTotal = $summary['previous_total'];
    $totalDelta = $delta($total, $previousTotal);
    $diff = $total - $previousTotal;
    $average = $summary['active_members'] > 0 ? intdiv($total, $summary['active_members']) : 0;
    $memberMax = max(1, ...array_map(fn ($r) => max($r['seconds'], $r['previous']), $summary['members'] ?: [['seconds' => 0, 'previous' => 0]]));
    $dayMax = max(1, ...array_map(fn ($r) => max($r['seconds'], $r['previous']), $summary['days']));
    $projectTotal = max(1, array_sum(array_column($summary['projects'], 'seconds')));
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $range = $summary['start']->day.' '.$months[$summary['start']->month - 1].' – '.$summary['end']->day.' '.$months[$summary['end']->month - 1].' '.$summary['end']->year;
    $rows = count($summary['members']);
    $rowHeight = $rows > 20 ? 18 : ($rows > 16 ? 21 : 24);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @font-face { font-family: 'Outfit'; src: url('outfit.ttf'); font-weight: 100 900; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { background: #ffffff; }
        body {
            width: 794px; height: 1123px; overflow: hidden;
            font-family: 'Outfit', Helvetica, Arial, sans-serif; color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }
        .page { position: relative; height: 1123px; padding: 34px 44px 0; }
        .band {
            position: absolute; inset: 0 0 auto 0; height: 210px;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 55%, #ffffff 100%);
        }
        .top { position: relative; display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; color: #0f172a; }
        .logo {
            width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(145deg, #38bdf8, #0284c7); color: #fff; font-weight: 700; font-size: 17px;
        }
        .brand b { color: #0284c7; font-weight: 700; }
        .week-pill {
            font-size: 12px; font-weight: 600; color: #0369a1; background: #ffffff; border: 1px solid #bae6fd;
            padding: 6px 12px; border-radius: 999px;
        }
        h1 { position: relative; margin-top: 22px; font-size: 34px; line-height: 1.05; font-weight: 700; letter-spacing: -0.5px; }
        .sub { position: relative; margin-top: 6px; font-size: 15px; color: #475569; }

        .kpis { position: relative; display: grid; grid-template-columns: 1.35fr 1fr 1fr 1fr; gap: 12px; margin-top: 22px; }
        .alert { margin-top: 12px; display: flex; gap: 8px; align-items: center; font-size: 12px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 8px 12px; }
        .alert b { font-weight: 600; }
        .kpi {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .kpi.main { background: #0f172a; border-color: #0f172a; color: #ffffff; }
        .kpi .label { font-size: 11.5px; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: .6px; }
        .kpi.main .label { color: #94a3b8; }
        .kpi .value { margin-top: 6px; font-size: 25px; font-weight: 700; letter-spacing: -0.3px; }
        .kpi .note { margin-top: 3px; font-size: 12px; color: #64748b; }
        .kpi.main .note { color: #cbd5e1; }
        .chip { display: inline-block; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; margin-left: 6px; vertical-align: 4px; }
        .chip.up { background: #dcfce7; color: #15803d; }
        .chip.down { background: #fee2e2; color: #b91c1c; }
        .chip.flat { background: #f1f5f9; color: #475569; }
        .kpi.main .chip.up { background: rgba(34, 197, 94, .18); color: #86efac; }
        .kpi.main .chip.down { background: rgba(239, 68, 68, .2); color: #fca5a5; }
        .kpi.main .chip.flat { background: rgba(148, 163, 184, .2); color: #e2e8f0; }

        .grid { display: grid; grid-template-columns: 1.12fr 1fr; gap: 14px; margin-top: 14px; }
        .card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 18px; background: #ffffff; }
        .card h2 { font-size: 14px; font-weight: 700; display: flex; justify-content: space-between; align-items: baseline; }
        .card h2 span { font-size: 11px; font-weight: 500; color: #94a3b8; }
        .legend { display: flex; gap: 12px; font-size: 11px; color: #64748b; margin-top: 4px; }
        .legend i { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }

        .person { display: grid; grid-template-columns: 112px 1fr 58px 50px; align-items: center; gap: 8px; font-size: 12.5px; }
        .person .name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
        .person.zero .name { color: #94a3b8; font-weight: 400; }
        .track { position: relative; height: 8px; border-radius: 99px; background: #f1f5f9; }
        .fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 99px; background: linear-gradient(90deg, #38bdf8, #0284c7); }
        .mark { position: absolute; top: -3px; width: 2px; height: 14px; border-radius: 2px; background: #94a3b8; }
        .hours { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
        .person.zero .hours { color: #cbd5e1; font-weight: 500; }
        .trend { text-align: right; font-size: 10.5px; font-weight: 600; }
        .trend.up { color: #16a34a; } .trend.down { color: #dc2626; } .trend.flat { color: #94a3b8; }

        .project { display: grid; grid-template-columns: 1fr 52px 40px; gap: 8px; align-items: center; font-size: 12.5px; margin-top: 9px; }
        .project .pname { display: flex; align-items: center; gap: 7px; white-space: nowrap; overflow: hidden; }
        .dot { flex: none; width: 9px; height: 9px; border-radius: 3px; }
        .project .ptrack { grid-column: 1 / -1; height: 6px; border-radius: 99px; background: #f1f5f9; margin-top: -4px; position: relative; }
        .project .pfill { position: absolute; inset: 0 auto 0 0; border-radius: 99px; }
        .pct { text-align: right; color: #64748b; font-size: 11.5px; }

        .days { display: flex; align-items: flex-end; justify-content: space-between; height: 118px; margin-top: 14px; padding: 0 2px; border-bottom: 1px solid #e2e8f0; }
        .day { flex: 1; display: flex; justify-content: center; align-items: flex-end; gap: 3px; height: 100%; }
        .bar { width: 11px; border-radius: 4px 4px 1px 1px; }
        .bar.now { background: linear-gradient(180deg, #38bdf8, #0284c7); }
        .bar.before { background: #cbd5e1; }
        .day-labels { display: flex; justify-content: space-between; padding: 0 2px; margin-top: 5px; }
        .day-labels div { flex: 1; text-align: center; font-size: 10.5px; color: #64748b; }
        .day-labels b { display: block; color: #0f172a; font-size: 10.5px; font-weight: 600; }

        .meetings { display: grid; grid-template-columns: repeat({{ min(6, max(1, count($summary['meetings']))) }}, 1fr); gap: 8px; margin-top: 10px; }
        .meeting { border: 1px solid #e2e8f0; border-radius: 12px; padding: 9px 10px; }
        .meeting .mname { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meeting .mwhen { font-size: 11px; color: #94a3b8; }
        .meeting .mcount { margin-top: 4px; display: flex; justify-content: space-between; align-items: baseline; font-size: 11px; color: #94a3b8; }
        .meeting .mcount b { color: #0f172a; font-size: 13px; }
        .meeting .track { height: 6px; margin-top: 5px; }
        .foot { position: absolute; left: 44px; right: 44px; bottom: 16px; display: flex; justify-content: space-between; font-size: 10.5px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="page">
    <div class="band"></div>
    <div class="top">
        <div class="brand"><div class="logo">V</div><span><b>VALO's</b> Time</span></div>
        <div class="week-pill">Semana {{ $summary['week_number'] }} · {{ $range }}</div>
    </div>
    <h1>Resumen de la semana</h1>
    <p class="sub">{{ $summary['organization'] }} · comparado con la semana anterior</p>

    <div class="kpis">
        <div class="kpi main">
            <div class="label">Horas esta semana</div>
            <div class="value">{{ $fmt($total) }}<span class="chip {{ $totalDelta['class'] }}">{{ $totalDelta['text'] }}</span></div>
            <div class="note">{{ $diff >= 0 ? '+' : '−' }}{{ $fmt(abs($diff)) }} que la semana pasada</div>
        </div>
        <div class="kpi">
            <div class="label">Semana pasada</div>
            <div class="value">{{ $fmt($previousTotal) }}</div>
            <div class="note">total del equipo</div>
        </div>
        <div class="kpi">
            <div class="label">Personas con horas</div>
            <div class="value">{{ $summary['active_members'] }}<span style="font-size:15px;color:#94a3b8;font-weight:500"> / {{ $summary['members_count'] }}</span></div>
            <div class="note">registraron algo</div>
        </div>
        <div class="kpi">
            <div class="label">Media por persona</div>
            <div class="value">{{ $fmt($average) }}</div>
            <div class="note">entre quienes registraron</div>
        </div>
    </div>

    @if(count($summary['running']) > 0)
        <div class="alert">⏱ <span><b>Cronómetro sin parar:</b> {{ implode(', ', $summary['running']) }} — sus horas no cuentan hasta que lo detengan o corrijan.</span></div>
    @endif

    <div class="grid">
        <div class="card">
            <h2>Por persona <span>esta semana · <i style="display:inline-block;width:2px;height:9px;background:#94a3b8;vertical-align:-1px"></i> semana pasada</span></h2>
            <div style="margin-top: 8px;">
                @foreach($summary['members'] as $row)
                    @php $d = $delta($row['seconds'], $row['previous']); @endphp
                    <div class="person {{ $row['seconds'] === 0 ? 'zero' : '' }}" style="height: {{ $rowHeight }}px;">
                        <div class="name">{{ $row['name'] }}</div>
                        <div class="track">
                            <div class="fill" style="width: {{ round($row['seconds'] / $memberMax * 100, 2) }}%;"></div>
                            @if($row['previous'] > 0)
                                <div class="mark" style="left: calc({{ round($row['previous'] / $memberMax * 100, 2) }}% - 1px);"></div>
                            @endif
                        </div>
                        <div class="hours">{{ $row['seconds'] > 0 ? $fmt($row['seconds']) : '0 h' }}</div>
                        <div class="trend {{ $d['class'] }}">{{ $d['text'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div class="card">
                <h2>Por proyecto <span>% del tiempo</span></h2>
                @forelse($summary['projects'] as $row)
                    <div class="project">
                        <div class="pname"><span class="dot" style="background: {{ $row['color'] }}"></span>{{ $row['name'] }}</div>
                        <div class="hours">{{ $fmt($row['seconds']) }}</div>
                        <div class="pct">{{ round($row['seconds'] / $projectTotal * 100) }} %</div>
                        <div class="ptrack"><div class="pfill" style="width: {{ round($row['seconds'] / $projectTotal * 100, 2) }}%; background: {{ $row['color'] }};"></div></div>
                    </div>
                @empty
                    <p style="margin-top: 10px; font-size: 12.5px; color: #94a3b8;">Sin horas esta semana.</p>
                @endforelse
            </div>
            <div class="card">
                <h2>Día a día</h2>
                <div class="legend"><span><i style="background:#0284c7"></i>Esta semana</span><span><i style="background:#cbd5e1"></i>Semana pasada</span></div>
                <div class="days">
                    @foreach($summary['days'] as $day)
                        <div class="day">
                            <div class="bar before" style="height: {{ max(1, round($day['previous'] / $dayMax * 100, 2)) }}%;"></div>
                            <div class="bar now" style="height: {{ max(1, round($day['seconds'] / $dayMax * 100, 2)) }}%;"></div>
                        </div>
                    @endforeach
                </div>
                <div class="day-labels">
                    @foreach($summary['days'] as $day)
                        <div>{{ $day['label'] }}<b>{{ $day['seconds'] > 0 ? $fmt($day['seconds']) : '–' }}</b></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if(count($summary['meetings']) > 0)
        <div class="card" style="margin-top: 14px;">
            <h2>Reuniones de la semana <span>personas que asistieron (dieron OK o la registraron)</span></h2>
            <div class="meetings">
                @foreach($summary['meetings'] as $meeting)
                    <div class="meeting">
                        <div class="mname">{{ $meeting['name'] }}</div>
                        <div class="mcount"><span>{{ $meeting['when'] }}</span><b>{{ $meeting['attended'] }}/{{ $meeting['expected'] }}</b></div>
                        <div class="track"><div class="fill" style="width: {{ $meeting['expected'] > 0 ? round($meeting['attended'] / $meeting['expected'] * 100, 2) : 0 }}%;"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="foot">
        <span>Generado el {{ now()->setTimezone($timezone)->format('d/m/Y H:i') }}</span>
        <span>valos-time.onrender.com</span>
    </div>
</div>
</body>
</html>
