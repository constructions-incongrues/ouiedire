"""Normalise le balisage des playlists. Appele par bin/migration-prepare."""
import html, os, re, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import importlib.util as _u
from importlib.machinery import SourceFileLoader as _L
_p = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'migration-emission')
_s = _u.spec_from_loader('migem', _L('migem', _p))
m = _u.module_from_spec(_s); _s.loader.exec_module(m)

ORPHAN = re.compile(r'^(?:\s|</\s*[a-zA-Z][\w-]*\s*>)+')

def norm(s):
    s = re.sub(r'\s+', ' ', s)
    s = re.sub(r'>\s+', '>', s)
    s = re.sub(r'\s+<', '<', s)
    return s.strip()

def canonical(inner):
    """Forme canonique d'un <li> interpretable, ou None s'il releve de C."""
    e = m.classify(inner)
    if e['form'] == 'C':
        return None
    tm = m.TIME.search(inner)
    after = inner[tm.end():]
    nxt = m.NEXT_TAG.search(after)
    span = m.SPAN.match(after[nxt.start():])
    rest = after[nxt.start() + span.end():]
    rest = ORPHAN.sub('', rest).strip()        # </a> et </span> orphelins
    if rest[:1] == '-':
        rest = rest[1:].strip()
    elif rest and not rest[0].isalnum() and len(rest) > 1 and rest[1:2] == '-':
        rest = rest[2:].strip()                # tiret precede d'un caractere invisible
    artist = e['artist'].replace('<', '').strip()
    out = '<a class="mejs-smartplaylist-time">%s</a><span>%s</span>' % (
        html.escape(e['time'], quote=False), html.escape(artist, quote=False))
    if rest:
        out += ' - ' + rest
    return out

changed_files = changed_lines = 0
for slug, path in m.shows():
    f = os.path.join(path, 'playlist.html')
    if not os.path.exists(f):
        continue
    src = m.read(f)
    out, cursor, hits = [], 0, 0
    for match in m.LI.finditer(src):
        inner = match.group(1)
        canon = canonical(inner)
        if canon is None or norm(canon) == norm(inner):
            continue
        out.append(src[cursor:match.start(1)])
        out.append(canon)
        cursor = match.end(1)
        hits += 1
    if hits:
        out.append(src[cursor:])
        with open(f, 'w', encoding='utf-8') as handle:
            handle.write(''.join(out))
        changed_files += 1
        changed_lines += hits
print('%d lignes normalisees dans %d emissions' % (changed_lines, changed_files))
