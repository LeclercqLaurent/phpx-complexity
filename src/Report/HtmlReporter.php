<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Baseline\Comparison;
use PhpxComplexity\Baseline\DeltaCategory;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Qa\QaToolResult;

/**
 * Rapport HTML autonome : un seul fichier, CSS et JS INLINE, aucune ressource
 * externe ni appel réseau (conforme au « hors-ligne strict »). Consommateur du
 * même contrat de données que JsonReporter — aucune logique d'analyse ici.
 *
 * Pièce maîtresse : un nuage de points lentille-vs-lentille (rangs centiles) qui
 * rend visible la DIVERGENCE — les points loin de la diagonale sont les méthodes
 * qu'une métrique isolée laisserait passer. Reste factuel : valeurs brutes,
 * compteurs, seuils. Aucun score.
 *
 * @phpstan-type Summary array{files: int, methods: int, methodsInViolation: int, totalViolations: int, parseErrors: int}
 */
final class HtmlReporter
{
    /**
     * @param list<Lens> $lenses
     */
    public function __construct(
        private readonly array $lenses,
        private readonly Config $config,
    ) {
    }

    public function render(AuditResult $audit, ?Comparison $comparison = null): string
    {
        $data = $this->buildData($audit);
        $json = (string) json_encode(
            $data,
            \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );

        $css = $this->css();
        $script = $this->script();
        $summary = $data['summary'];
        $generated = $this->headerHtml($summary, $audit->parseErrors);
        $qaSection = [] !== $audit->qaResults ? $this->qaHtml($audit->qaResults) : '';
        $coverageSection = (null !== $audit->coverage || null !== $audit->presence) ? $this->coverageHtml($audit->coverage, $audit->presence) : '';
        $baselineSection = null !== $comparison ? $this->baselineHtml($comparison) : '';

        return <<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>phpx-complexity — rapport</title>
            <style>{$css}</style>
            </head>
            <body>
            <header class="hd">
              <h1>phpx-complexity</h1>
              <p class="sub">Audit de complexité multi-lentilles — rapport factuel, hors-ligne.</p>
            </header>
            {$generated}
            <section class="card">
              <h2>Divergence — lentille contre lentille</h2>
              <p class="hint">Axes = rangs centiles [0,1]. Un point loin de la diagonale diverge :
              élevé sur un axe, bas sur l'autre — l'angle mort d'une métrique isolée.</p>
              <div class="axes">
                <label>X <select id="axisX"></select></label>
                <label>Y <select id="axisY"></select></label>
                <label class="ck"><input type="checkbox" id="onlyViol"> Violations seules</label>
              </div>
              <div id="scatter"></div>
            </section>
            <section class="card">
              <h2>Méthodes</h2>
              <p class="hint">Cliquez un en-tête pour trier. <span class="viol-key">!</span> = seuil dépassé.</p>
              <div class="tablewrap"><table id="methods"></table></div>
            </section>
            {$baselineSection}
            {$qaSection}
            {$coverageSection}
            <script type="application/json" id="data">{$json}</script>
            <script>{$script}</script>
            </body>
            </html>
            HTML;
    }

    /**
     * @param Summary      $summary
     * @param list<string> $parseErrors
     */
    private function headerHtml(array $summary, array $parseErrors): string
    {
        $cards = [
            ['Fichiers', (string) $summary['files']],
            ['Méthodes', (string) $summary['methods']],
            ['Méthodes en dépassement', (string) $summary['methodsInViolation']],
            ['Dépassements (total)', (string) $summary['totalViolations']],
        ];
        $cells = '';
        foreach ($cards as [$label, $value]) {
            $cells .= sprintf('<div class="stat"><span class="num">%s</span><span class="lbl">%s</span></div>', $this->e($value), $this->e($label));
        }

        $legend = '';
        foreach ($this->lenses as $lens) {
            $ref = '' !== $lens->reference() ? sprintf(' <span class="ref">%s</span>', $this->e($lens->reference())) : '';
            $legend .= sprintf(
                '<li><div class="ltop"><span><code>%s</code>%s — %s</span><span class="thr">seuil %s</span></div><p class="ldesc">%s</p></li>',
                $this->e($lens->key()),
                $ref,
                $this->e($lens->label()),
                $this->e($this->num($this->config->threshold($lens->key()))),
                $this->e($lens->description()),
            );
        }

        $errors = '';
        if ([] !== $parseErrors) {
            $items = '';
            foreach ($parseErrors as $err) {
                $items .= sprintf('<li>%s</li>', $this->e($err));
            }
            $errors = sprintf('<div class="card warn"><h2>Erreurs de parsing (%d)</h2><ul class="errs">%s</ul></div>', count($parseErrors), $items);
        }

        return <<<HTML
            <section class="stats">{$cells}</section>
            <section class="card">
              <h2>Lentilles</h2>
              <ul class="legend">{$legend}</ul>
            </section>
            {$errors}
            HTML;
    }

    /**
     * @param list<QaToolResult> $qaResults
     */
    private function qaHtml(array $qaResults): string
    {
        $total = count($qaResults);
        $present = count(array_filter($qaResults, static fn (QaToolResult $r) => $r->present));
        $missing = [];
        $rows = '';
        foreach ($qaResults as $r) {
            if ($r->required && !$r->present) {
                $missing[] = $r->tool->label;
            }
            $state = $r->present
                ? '<span class="ok">présent</span>'
                : ('<span class="' . ($r->required ? 'viol-key' : 'mut') . '">' . ($r->required ? 'manquant (requis)' : 'absent') . '</span>');
            $rows .= sprintf(
                '<tr><td class="meth">%s</td><td>%s</td><td>%s</td><td class="meth">%s</td></tr>',
                $this->e($r->tool->label),
                $this->e($r->tool->category),
                $state,
                $this->e(implode(', ', $r->evidence)),
            );
        }
        $missingNote = [] !== $missing
            ? sprintf('<p class="hint viol-key">Outils requis manquants : %s</p>', $this->e(implode(', ', $missing)))
            : '';

        return <<<HTML
            <section class="card">
              <h2>Outils de QA — {$present}/{$total} présents</h2>
              {$missingNote}
              <div class="tablewrap"><table class="static"><tr><th>Outil</th><th>Catégorie</th><th>État</th><th>Preuves</th></tr>{$rows}</table></div>
            </section>
            HTML;
    }

    private function coverageHtml(?CoverageReport $coverage, ?TestPresence $presence): string
    {
        $cards = '';
        if (null !== $coverage && $coverage->found) {
            $line = null !== $coverage->linePercent ? $this->num($coverage->linePercent) . ' %' : 'n/d';
            $cards .= $this->statCard('Couverture lignes', $line);
            if (null !== $coverage->methodPercent) {
                $cards .= $this->statCard('Couverture méthodes', $this->num($coverage->methodPercent) . ' %');
            }
            $cards .= $this->statCard('Format', $this->e((string) $coverage->format));
        } elseif (null !== $coverage) {
            $cards .= $this->statCard('Couverture', 'aucun rapport');
        }
        if (null !== $presence) {
            $cards .= $this->statCard('Classes testées', $presence->testedClasses() . '/' . $presence->sourceClasses);
            $cards .= $this->statCard('Méthodes de test', (string) $presence->testMethods);
        }

        return <<<HTML
            <section class="card">
              <h2>Tests &amp; couverture</h2>
              <p class="hint">Faits statiques : la présence d'un outil ne garantit ni des tests, ni leur couverture.</p>
              <div class="stats inner">{$cards}</div>
            </section>
            HTML;
    }

    private function statCard(string $label, string $value): string
    {
        return sprintf('<div class="stat"><span class="num">%s</span><span class="lbl">%s</span></div>', $value, $this->e($label));
    }

    /**
     * @return array{summary: Summary, lenses: list<array<string,mixed>>, methods: list<array<string,mixed>>}
     */
    private function buildData(AuditResult $audit): array
    {
        $results = $audit->results;
        usort($results, static fn (MethodResult $a, MethodResult $b) => $b->divergence <=> $a->divergence);

        $methods = [];
        $methodsInViolation = 0;
        $totalViolations = 0;
        foreach ($results as $r) {
            $violations = [];
            foreach ($this->lenses as $lens) {
                if ($r->metric($lens->key()) > $this->config->threshold($lens->key())) {
                    $violations[] = $lens->key();
                }
            }
            $totalViolations += count($violations);
            $methodsInViolation += [] !== $violations ? 1 : 0;
            $methods[] = [
                'file' => $r->file,
                'name' => $r->name,
                'line' => $r->line,
                'metrics' => $r->metrics,
                'percentile' => $r->percentile,
                'divergence' => round($r->divergence, 4),
                'violations' => $violations,
            ];
        }

        return [
            'summary' => [
                'files' => $audit->files,
                'methods' => count($results),
                'methodsInViolation' => $methodsInViolation,
                'totalViolations' => $totalViolations,
                'parseErrors' => count($audit->parseErrors),
            ],
            'lenses' => $this->lensesPayload(),
            'methods' => $methods,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lensesPayload(): array
    {
        $payload = [];
        foreach ($this->lenses as $lens) {
            $payload[] = [
                'key' => $lens->key(),
                'label' => $lens->label(),
                'reference' => $lens->reference(),
                'description' => $lens->description(),
                'threshold' => $this->config->threshold($lens->key()),
            ];
        }

        return $payload;
    }

    private function baselineHtml(Comparison $comparison): string
    {
        $rows = $this->deltaRows($comparison, DeltaCategory::NewViolation, 'nouvelle')
            . $this->deltaRows($comparison, DeltaCategory::Worsened, 'aggravée')
            . $this->deltaRows($comparison, DeltaCategory::Resolved, 'résolue');
        if ('' === $rows) {
            $rows = '<tr><td colspan="5" class="mut">Aucun écart : rien n\'a bougé depuis l\'instantané.</td></tr>';
        }

        $source = $this->e($comparison->source);
        $regressions = $comparison->regressionCount();
        $appeared = count($comparison->appeared);
        $disappeared = count($comparison->disappeared);

        return <<<HTML
            <section class="card">
              <h2>Baseline — écarts par rapport à {$source}</h2>
              <p class="hint">{$regressions} régression(s) · {$appeared} méthode(s) apparue(s) · {$disappeared} disparue(s).
              Les violations héritées et inchangées ne figurent pas : seul le mouvement est montré.</p>
              <div class="tablewrap"><table class="static"><tr><th>Nature</th><th>Lentille</th><th>Avant</th><th>Après</th><th>Méthode</th></tr>{$rows}</table></div>
            </section>
            HTML;
    }

    private function deltaRows(Comparison $comparison, DeltaCategory $category, string $label): string
    {
        $class = DeltaCategory::Resolved === $category ? 'ok' : 'viol-key';
        $rows = '';
        foreach ($comparison->of($category) as $delta) {
            $rows .= sprintf(
                '<tr><td><span class="%s">%s</span></td><td class="meth">%s</td><td>%s</td><td>%s</td><td class="meth">%s::%s</td></tr>',
                $class,
                $this->e($label),
                $this->e($delta->lens),
                null === $delta->before ? '—' : $this->e($this->num($delta->before)),
                $this->e($this->num($delta->after)),
                $this->e($delta->file),
                $this->e($delta->name),
            );
        }

        return $rows;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }

    private function num(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : number_format($value, 2);
    }

    private function css(): string
    {
        return <<<'CSS'
            :root{--bg:#0f1115;--panel:#171a21;--line:#262b35;--fg:#e6e9ef;--mut:#8b93a3;--accent:#5ac8fa;--viol:#ff5c5c;--ok:#4cd07d}
            *{box-sizing:border-box}
            body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
            .hd{padding:28px 24px 8px}
            h1{margin:0;font-size:22px;letter-spacing:.5px}
            .sub{margin:4px 0 0;color:var(--mut)}
            h2{margin:0 0 12px;font-size:15px;color:var(--accent);font-weight:600}
            .stats{display:flex;gap:12px;flex-wrap:wrap;padding:16px 24px}
            .stat{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:14px 18px;min-width:140px}
            .stat .num{display:block;font-size:26px;font-weight:700}
            .stat .lbl{color:var(--mut);font-size:12px}
            .card{background:var(--panel);border:1px solid var(--line);border-radius:12px;margin:16px 24px;padding:18px}
            .card.warn{border-color:var(--viol)}
            .hint{color:var(--mut);margin:0 0 12px;font-size:12px}
            .legend{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:10px}
            .legend li{background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:10px 12px}
            .legend code{color:var(--accent)}
            .legend .ref{color:var(--mut);font-size:11px;margin-left:4px}
            .legend .ltop{display:flex;justify-content:space-between;gap:8px;align-items:baseline}
            .legend .thr{color:var(--mut);white-space:nowrap}
            .legend .ldesc{margin:6px 0 0;color:var(--mut);font-size:12px;line-height:1.5}
            .errs{margin:0;color:var(--viol)}
            .axes{display:flex;gap:18px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
            .axes select{background:var(--bg);color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 8px;font:inherit}
            .axes .ck{color:var(--mut)}
            #scatter svg{width:100%;height:auto;display:block}
            .pt{fill:var(--mut);opacity:.7}
            .pt.v{fill:var(--viol);opacity:.95}
            .diag{stroke:var(--line);stroke-dasharray:4 4}
            .ax{stroke:var(--line)}
            .axlbl{fill:var(--mut);font-size:11px}
            .tablewrap{overflow-x:auto}
            table{border-collapse:collapse;width:100%;font-size:13px}
            th,td{text-align:right;padding:6px 10px;border-bottom:1px solid var(--line);white-space:nowrap}
            th:last-child,td:last-child{text-align:left}
            th{cursor:pointer;color:var(--mut);user-select:none;position:sticky;top:0;background:var(--panel)}
            th.sorted::after{content:" ▾";color:var(--accent)}
            th.asc.sorted::after{content:" ▴"}
            td.v{color:var(--viol);font-weight:700}
            tr:hover td{background:#1d212b}
            table.static th{cursor:default;position:static}
            .stats.inner{padding:4px 0 0}
            .meth{color:var(--fg)}
            .meth .ln{color:var(--mut)}
            .viol-key{color:var(--viol);font-weight:700}
            .ok{color:var(--ok);font-weight:600}
            .mut{color:var(--mut)}
            CSS;
    }

    private function script(): string
    {
        return <<<'JS'
            (function(){
              var D=JSON.parse(document.getElementById('data').textContent);
              var L=D.lenses,M=D.methods;
              var thr={}; L.forEach(function(l){thr[l.key]=l.threshold;});
              function num(v){return Math.floor(v)===v?String(v):v.toFixed(2);}
              function pc(m,k){return (m.percentile&&m.percentile[k]!=null)?m.percentile[k]:0;}
              function val(m,k){return (m.metrics&&m.metrics[k]!=null)?m.metrics[k]:0;}
              function isViol(m){return m.violations&&m.violations.length>0;}

              // ---- table (sortable) ----
              var tbl=document.getElementById('methods');
              var sortKey='divergence',asc=false;
              function head(){
                var tr=document.createElement('tr');
                L.forEach(function(l){tr.appendChild(th(l.key,l.key.slice(0,6).toUpperCase()));});
                tr.appendChild(th('divergence','Δ'));
                var m=document.createElement('th');m.textContent='Méthode';m.dataset.k='name';
                m.onclick=function(){setSort('name');};tr.appendChild(m);
                return tr;
              }
              function descOf(k){for(var i=0;i<L.length;i++)if(L[i].key===k)return L[i].description||'';return '';}
              function th(k,label){var e=document.createElement('th');e.textContent=label;e.dataset.k=k;e.title=descOf(k);e.onclick=function(){setSort(k);};return e;}
              function setSort(k){if(sortKey===k){asc=!asc;}else{sortKey=k;asc=false;}draw();}
              function cmp(a,b){
                var av,bv;
                if(sortKey==='divergence'){av=a.divergence;bv=b.divergence;}
                else if(sortKey==='name'){av=a.file+a.name;bv=b.file+b.name;return asc?(av<bv?-1:av>bv?1:0):(av>bv?-1:av<bv?1:0);}
                else{av=val(a,sortKey);bv=val(b,sortKey);}
                return asc?av-bv:bv-av;
              }
              function draw(){
                tbl.innerHTML='';
                var h=head();tbl.appendChild(h);
                Array.prototype.slice.call(h.children).forEach(function(c){
                  if(c.dataset.k===sortKey){c.classList.add('sorted');if(asc)c.classList.add('asc');}
                });
                M.slice().sort(cmp).forEach(function(m){
                  var tr=document.createElement('tr');
                  L.forEach(function(l){
                    var td=document.createElement('td');var over=val(m,l.key)>thr[l.key];
                    td.textContent=num(val(m,l.key))+(over?' !':'');if(over)td.classList.add('v');
                    tr.appendChild(td);
                  });
                  var dv=document.createElement('td');dv.textContent=m.divergence.toFixed(2);tr.appendChild(dv);
                  var nm=document.createElement('td');nm.className='meth';
                  nm.appendChild(document.createTextNode(m.file+'::'+m.name+' '));
                  var ln=document.createElement('span');ln.className='ln';ln.textContent='(l.'+m.line+')';
                  nm.appendChild(ln);tr.appendChild(nm);
                  tbl.appendChild(tr);
                });
              }

              // ---- scatter (percentiles) ----
              var sx=document.getElementById('axisX'),sy=document.getElementById('axisY');
              L.forEach(function(l,i){
                [sx,sy].forEach(function(s){var o=document.createElement('option');o.value=l.key;o.textContent=l.label;s.appendChild(o);});
              });
              sx.selectedIndex=0;sy.selectedIndex=Math.min(3,L.length-1);
              var only=document.getElementById('onlyViol');
              [sx,sy,only].forEach(function(el){el.addEventListener('change',scatter);});
              var NS='http://www.w3.org/2000/svg';
              function el(n,a){var e=document.createElementNS(NS,n);for(var k in a)e.setAttribute(k,a[k]);return e;}
              function scatter(){
                var W=720,H=420,pad=48,kx=sx.value,ky=sy.value;
                var svg=el('svg',{viewBox:'0 0 '+W+' '+H});
                var x0=pad,x1=W-pad,y0=H-pad,y1=pad;
                svg.appendChild(el('line',{class:'diag',x1:x0,y1:y0,x2:x1,y2:y1}));
                svg.appendChild(el('line',{class:'ax',x1:x0,y1:y0,x2:x1,y2:y0}));
                svg.appendChild(el('line',{class:'ax',x1:x0,y1:y0,x2:x0,y2:y1}));
                var lx=el('text',{class:'axlbl',x:(x0+x1)/2,y:H-12});lx.textContent=labelOf(kx)+' (rang)';svg.appendChild(lx);
                var ly=el('text',{class:'axlbl',x:14,y:(y0+y1)/2,transform:'rotate(-90 14 '+(y0+y1)/2+')'});ly.textContent=labelOf(ky)+' (rang)';svg.appendChild(ly);
                M.forEach(function(m){
                  if(only.checked&&!isViol(m))return;
                  var px=x0+pc(m,kx)*(x1-x0),py=y0-pc(m,ky)*(y0-y1);
                  var c=el('circle',{class:'pt'+(isViol(m)?' v':''),cx:px.toFixed(1),cy:py.toFixed(1),r:4});
                  var t=el('title',{});t.textContent=m.file+'::'+m.name+'  '+labelOf(kx)+'='+num(val(m,kx))+', '+labelOf(ky)+'='+num(val(m,ky));
                  c.appendChild(t);svg.appendChild(c);
                });
                var box=document.getElementById('scatter');box.innerHTML='';box.appendChild(svg);
              }
              function labelOf(k){for(var i=0;i<L.length;i++)if(L[i].key===k)return L[i].label;return k;}

              draw();scatter();
            })();
            JS;
    }
}
