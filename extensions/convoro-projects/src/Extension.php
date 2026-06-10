<?php

namespace Convoro\Ext\Projects;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Projects — first-party Convoro extension.
 *
 * A member showcase: people publish their projects (title, link, image,
 * description) which render as cards on a themed /projects page. Ships a
 * "latest projects" forum sidebar widget and admin moderation.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/projects', fn (Request $r) => response(self::page($r)));

            Route::get('/api/ext/projects/latest', function () {
                $rows = DB::table('projects')->join('users', 'users.id', '=', 'projects.user_id')
                    ->orderByDesc('projects.id')->limit(4)
                    ->get(['projects.id', 'projects.title', 'projects.image', 'projects.url', 'users.name as author']);

                return response()->json(['projects' => $rows]);
            });
        });

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/projects', function (Request $request) {
                $data = $request->validate([
                    'title' => ['required', 'string', 'max:160'],
                    'tagline' => ['nullable', 'string', 'max:200'],
                    'description' => ['nullable', 'string', 'max:4000'],
                    'url' => ['nullable', 'url', 'max:300'],
                    'image' => ['nullable', 'string', 'max:2048'],
                ]);
                DB::table('projects')->insert([
                    'user_id' => $request->user()->id,
                    'title' => $data['title'], 'tagline' => $data['tagline'] ?? null,
                    'description' => $data['description'] ?? null,
                    'url' => $data['url'] ?? null, 'image' => $data['image'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return redirect('/projects');
            });

            Route::delete('/projects/{id}', function (Request $request, int $id) {
                $p = DB::table('projects')->find($id);
                abort_if(! $p, 404);
                abort_unless($p->user_id === $request->user()->id || $request->user()->is_admin, 403);
                DB::table('projects')->where('id', $id)->delete();

                return response()->json(['ok' => true]);
            });
        });

        Route::middleware(['web', 'auth', 'admin'])->get('/admin/ext/projects', fn () => response(self::adminPage()));
    }

    private static function page(Request $request): string
    {
        $user = Auth::user();
        $isAdmin = (bool) ($user?->is_admin);
        $csrf = csrf_token();
        $theme = \App\Support\Theme::css();
        $font = \App\Support\Theme::fontStack((string) Settings::get('theme.font', 'Inter'));
        $name = htmlspecialchars((string) Settings::get('site.name', 'Convoro'), ENT_QUOTES);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);

        $rows = DB::table('projects')->join('users', 'users.id', '=', 'projects.user_id')
            ->orderByDesc('projects.id')->limit(120)
            ->get(['projects.id', 'projects.title', 'projects.tagline', 'projects.description', 'projects.url', 'projects.image', 'projects.user_id', 'users.name as author']);

        $cards = '';
        foreach ($rows as $p) {
            $canDel = $user && ($p->user_id === $user->id || $isAdmin);
            $img = $p->image ? '<div class="thumb" style="background-image:url(\''.$e($p->image).'\')"></div>' : '<div class="thumb noimg">🚀</div>';
            $link = $p->url ? '<a class="visit" href="'.$e($p->url).'" target="_blank" rel="noopener nofollow">Visit ↗</a>' : '';
            $del = $canDel ? '<button class="del" data-id="'.$p->id.'" title="Delete">✕</button>' : '';
            $cards .= '<div class="card">'.$del.$img.'<div class="pad"><div class="t">'.$e($p->title).'</div>'
                .($p->tagline ? '<div class="tag">'.$e($p->tagline).'</div>' : '')
                .($p->description ? '<p class="desc">'.nl2br($e($p->description)).'</p>' : '')
                .'<div class="foot"><span class="by">by '.$e($p->author).'</span>'.$link.'</div></div></div>';
        }
        if (! $cards) {
            $cards = '<div class="empty">No projects yet — be the first to share one!</div>';
        }

        $form = '';
        if ($user) {
            $form = <<<FORM
<details class="submit"><summary>+ Submit a project</summary>
<div class="fcard">
  <label>Title</label><input id="p_title" maxlength="160">
  <label>Tagline</label><input id="p_tagline" maxlength="200">
  <label>Link (https://…)</label><input id="p_url" placeholder="https://">
  <label>Description</label><textarea id="p_desc" rows="3"></textarea>
  <label>Image</label>
  <div class="imgrow"><div id="p_prev" class="prev"></div>
  <label class="upbtn">Upload<input id="p_file" type="file" accept="image/*" hidden></label></div>
  <input id="p_image" type="hidden">
  <div style="margin-top:12px"><button class="btn" id="p_submit">Publish project</button><span id="p_msg" class="msg"></span></div>
</div></details>
FORM;
        } else {
            $form = '<p class="signin">Log in on the community to share your own project.</p>';
        }

        return <<<HTML
<!DOCTYPE html><html lang="en" data-theme="{$e(Settings::get('theme.mode', 'light'))}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Projects · {$name}</title>
<style>{$theme}
*{box-sizing:border-box}body{margin:0;font-family:{$font};background:rgb(var(--c-bg));color:rgb(var(--c-text))}
a{color:rgb(var(--c-primary))}
.bar{position:sticky;top:0;display:flex;align-items:center;gap:14px;padding:14px 20px;background:rgb(var(--c-surface));border-bottom:1px solid rgb(var(--c-border));z-index:10}
.bar b{font-weight:800}.bar .sp{flex:1}
.wrap{max-width:1040px;margin:0 auto;padding:32px 20px}
h1{font-size:28px;margin:0 0 4px}.sub{color:rgb(var(--c-muted));margin:0 0 24px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}
.card{position:relative;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);overflow:hidden;transition:transform .15s,box-shadow .15s}
.card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,.10)}
.thumb{height:150px;background-size:cover;background-position:center;background-color:rgb(var(--c-surface-2))}
.thumb.noimg{display:flex;align-items:center;justify-content:center;font-size:36px}
.pad{padding:16px}.t{font-weight:800;font-size:16px}.tag{color:rgb(var(--c-text-2));font-size:14px;margin-top:2px}
.desc{color:rgb(var(--c-text-2));font-size:13px;margin:10px 0 0;max-height:80px;overflow:hidden}
.foot{display:flex;align-items:center;margin-top:14px;padding-top:12px;border-top:1px solid rgb(var(--c-border))}
.by{color:rgb(var(--c-muted));font-size:13px}.visit{margin-left:auto;font-weight:700;font-size:13px}
.del{position:absolute;right:8px;top:8px;border:0;border-radius:50%;width:28px;height:28px;cursor:pointer;background:rgba(0,0,0,.45);color:#fff;font-size:13px}
.empty{padding:60px;text-align:center;color:rgb(var(--c-muted));border:1px dashed rgb(var(--c-border));border-radius:var(--c-radius,12px)}
.submit{margin:0 0 22px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);padding:8px 16px}
.submit summary{cursor:pointer;font-weight:700;padding:8px 0}.fcard label{display:block;font-size:13px;color:rgb(var(--c-text-2));margin:10px 0 4px}
.fcard input,.fcard textarea{width:100%;background:rgb(var(--c-bg));border:1px solid rgb(var(--c-border));border-radius:9px;color:rgb(var(--c-text));padding:9px 11px;font:inherit}
.imgrow{display:flex;align-items:center;gap:12px}.prev{width:80px;height:54px;border-radius:8px;background:rgb(var(--c-surface-2)) center/cover;border:1px solid rgb(var(--c-border))}
.upbtn{cursor:pointer;background:rgb(var(--c-surface-2));border:1px solid rgb(var(--c-border));border-radius:9px;padding:8px 14px;font-size:13px;font-weight:600}
.btn{border:0;border-radius:var(--c-radius-btn,9px);padding:10px 18px;font-weight:700;cursor:pointer;background:rgb(var(--c-primary));color:#fff}
.msg{margin-left:10px;color:rgb(var(--c-muted));font-size:13px}.signin{color:rgb(var(--c-muted))}
</style></head><body>
<div class="bar"><b>{$name}</b><span class="sp"></span><a href="/">← Community</a></div>
<div class="wrap">
<h1>Projects</h1><p class="sub">What our members are building.</p>
{$form}
<div class="grid">{$cards}</div>
</div>
<script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
document.querySelectorAll('.del').forEach(b=>b.addEventListener('click',async()=>{
  if(!confirm('Delete this project?'))return;
  await fetch('/projects/'+b.dataset.id,{method:'DELETE',headers:h});location.reload();
}));
const file=document.getElementById('p_file');
if(file){
  file.addEventListener('change',async e=>{
    const f=e.target.files[0];if(!f)return;
    const fd=new FormData();fd.append('file',f);
    const r=await fetch('/uploads/image',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
    const d=await r.json();if(d.url){document.getElementById('p_image').value=d.url;document.getElementById('p_prev').style.backgroundImage="url('"+d.url+"')";}
  });
  document.getElementById('p_submit').addEventListener('click',async()=>{
    const body={title:val('p_title'),tagline:val('p_tagline'),url:val('p_url')||null,description:val('p_desc'),image:document.getElementById('p_image').value||null};
    if(!body.title){document.getElementById('p_msg').textContent='Title is required';return;}
    const r=await fetch('/projects',{method:'POST',headers:h,body:JSON.stringify(body)});
    if(r.ok||r.redirected)location.href='/projects';
  });
}
function val(id){return document.getElementById(id).value.trim();}
</script></body></html>
HTML;
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $rows = DB::table('projects')->join('users', 'users.id', '=', 'projects.user_id')
            ->orderByDesc('projects.id')->limit(200)
            ->get(['projects.id', 'projects.title', 'projects.url', 'users.name as author']);
        $list = $rows->map(fn ($p) => '<div class="row"><div class="b"><b>'.htmlspecialchars($p->title).'</b>'
            .($p->url ? ' · <a href="'.htmlspecialchars($p->url).'" target="_blank">link</a>' : '')
            .'<div class="tag">by '.htmlspecialchars($p->author).'</div></div>'
            .'<button class="btn danger" onclick="del('.$p->id.')">Delete</button></div>')->implode('') ?: '<p style="color:#9aa0b8">No projects yet.</p>';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Projects · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:720px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:8px 18px}
.row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}.row:last-child{border-bottom:0}
.b{flex:1;min-width:0}.tag{font-size:12px;color:#9aa0b8}.btn{border:0;border-radius:9px;padding:8px 14px;font-weight:700;cursor:pointer}.btn.danger{background:transparent;color:#f87171}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.sp{flex:1}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Projects</h1><p class="sub">Member project showcase — moderate submissions. <a href="/projects" target="_blank">View page ↗</a></p></div><span class="sp"></span><a href="/admin/marketplace">← Marketplace</a></div>
<div class="card">{$list}</div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
async function del(id){if(!confirm('Delete this project?'))return;
await fetch('/projects/'+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});location.reload();}
</script></body></html>
HTML;
    }
}
