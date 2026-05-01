Folder asset opsional.

bgm/      : .mp3/.ogg + manifest.json (items:[{file,name}])
sfx/      : assets/sfx/box.mp3 utk pickup (global per-room).
arena/    : background arena .jpg/.png + manifest.json
            dipilih lewat dropdown 'Arena' di room (per-pemain, lokal).

boxes/    : skin pack untuk kotak (per-pemain, lokal).
            Struktur:
              boxes/manifest.json -> { packs: [{id,name,dir}], kinds: [...] }
              boxes/<dir>/normal.png
              boxes/<dir>/bonus.png
              boxes/<dir>/minus.png
              boxes/<dir>/penalty.png
              boxes/<dir>/freeze.png
              boxes/<dir>/slow.png
              boxes/<dir>/shock.png
              boxes/<dir>/boost.png
            Pack bawaan: nature/ (Element/Alam) & recycle/ (Sampah).
            Format: PNG transparan 128x128 px.

avatars/  : preset avatar pemain (per-pemain, lokal).
            avatars/manifest.json -> { items:[{file,name}] }
            Format: PNG 128x128, lingkaran (canvas akan auto-crop bulat).

CATATAN penting (avatar):
  Pilihan avatar saat ini disimpan di localStorage masing2 device dan
  hanya tampil di view sendiri (TODO: broadcast avatarId/avatarData
  via WS payload supaya pemain lain ikut melihat). Avatar render di
  game.js -> drawFromRenderState() pakai window.__AVATAR_IMGS__[seat].
