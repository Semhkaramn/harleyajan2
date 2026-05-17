"""
Telegram Grup Üye Takip Botu
- Kullanıcı adı, isim, soyisim değişikliklerini takip eder
- Değişiklikleri PostgreSQL veritabanına kaydeder
- Değişiklik olduğunda gruba bildirim gönderir

Heroku + GitHub deployment için hazırlanmıştır.
"""

import os
import asyncio
import logging
from datetime import datetime
from telethon import TelegramClient, events
from telethon.sessions import StringSession
from apscheduler.schedulers.asyncio import AsyncIOScheduler
import psycopg2
from psycopg2.extras import RealDictCursor

# Logging ayarları
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Environment Variables
API_ID = int(os.getenv('API_ID', '0'))
API_HASH = os.getenv('API_HASH', '')
SESSION_STRING = os.getenv('SESSION_STRING', '')
GROUP_ID = int(os.getenv('GROUP_ID', '0'))
NOTIFICATION_GROUP_ID = int(os.getenv('NOTIFICATION_GROUP_ID', '0'))
DATABASE_URL = os.getenv('DATABASE_URL', '')
CHECK_INTERVAL_MINUTES = int(os.getenv('CHECK_INTERVAL_MINUTES', '30'))
ADMIN_IDS = os.getenv('ADMIN_IDS', '')

def get_admin_ids() -> list:
    """Admin ID'lerini al"""
    if not ADMIN_IDS:
        return []
    return [int(x.strip()) for x in ADMIN_IDS.split(',') if x.strip()]

# ==================== VERİTABANI İŞLEMLERİ ====================

def get_db_connection():
    """PostgreSQL bağlantısı (Neon.tech)"""
    conn = psycopg2.connect(
        DATABASE_URL,
        sslmode='require',
        cursor_factory=RealDictCursor,
        connect_timeout=10
    )
    conn.autocommit = False
    return conn

def init_database():
    """Tabloları oluştur"""
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute('''
        CREATE TABLE IF NOT EXISTS members (
            user_id BIGINT PRIMARY KEY,
            username VARCHAR(255),
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    cur.execute('''
        CREATE TABLE IF NOT EXISTS changes (
            id SERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL,
            change_type VARCHAR(50) NOT NULL,
            old_value VARCHAR(255),
            new_value VARCHAR(255),
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    cur.execute('CREATE INDEX IF NOT EXISTS idx_changes_user_id ON changes(user_id)')
    cur.execute('CREATE INDEX IF NOT EXISTS idx_changes_date ON changes(changed_at DESC)')

    conn.commit()
    cur.close()
    conn.close()
    logger.info("Veritabanı hazır")

def get_member(user_id: int):
    """Üye bilgilerini al"""
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute('SELECT * FROM members WHERE user_id = %s', (user_id,))
    result = cur.fetchone()
    cur.close()
    conn.close()
    return dict(result) if result else None

def save_member(user_id: int, username: str, first_name: str, last_name: str):
    """Üye kaydet/güncelle"""
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute('''
        INSERT INTO members (user_id, username, first_name, last_name, last_updated)
        VALUES (%s, %s, %s, %s, CURRENT_TIMESTAMP)
        ON CONFLICT (user_id) DO UPDATE SET
            username = EXCLUDED.username,
            first_name = EXCLUDED.first_name,
            last_name = EXCLUDED.last_name,
            last_updated = CURRENT_TIMESTAMP
    ''', (user_id, username, first_name, last_name))
    conn.commit()
    cur.close()
    conn.close()

def save_change(user_id: int, change_type: str, old_value: str, new_value: str):
    """Değişiklik kaydet"""
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute('''
        INSERT INTO changes (user_id, change_type, old_value, new_value)
        VALUES (%s, %s, %s, %s)
    ''', (user_id, change_type, old_value, new_value))
    conn.commit()
    cur.close()
    conn.close()

# ==================== TELEGRAM İŞLEMLERİ ====================

client = TelegramClient(StringSession(SESSION_STRING), API_ID, API_HASH)

async def get_all_participants():
    """Tüm grup üyelerini al (40k+ için optimize)"""
    logger.info("Üyeler alınıyor...")
    participants = []

    try:
        async for user in client.iter_participants(GROUP_ID, aggressive=True):
            participants.append({
                'user_id': user.id,
                'username': user.username,
                'first_name': user.first_name or '',
                'last_name': user.last_name or ''
            })

            if len(participants) % 5000 == 0:
                logger.info(f"{len(participants)} üye alındı...")
                await asyncio.sleep(1)  # Rate limit

    except Exception as e:
        logger.error(f"Üye alma hatası: {e}")

    logger.info(f"Toplam {len(participants)} üye")
    return participants

async def check_for_changes():
    """Değişiklikleri kontrol et"""
    logger.info("Kontrol başlıyor...")

    participants = await get_all_participants()
    changes_detected = []
    new_members = 0

    for p in participants:
        existing = get_member(p['user_id'])

        if existing is None:
            save_member(p['user_id'], p['username'], p['first_name'], p['last_name'])
            new_members += 1
            continue

        changes = []

        # Username
        if existing['username'] != p['username']:
            old_val = existing['username'] or '(yok)'
            new_val = p['username'] or '(yok)'
            save_change(p['user_id'], 'username', old_val, new_val)
            changes.append(('K.Adı', old_val, new_val))

        # İsim
        if existing['first_name'] != p['first_name']:
            old_val = existing['first_name'] or '(yok)'
            new_val = p['first_name'] or '(yok)'
            save_change(p['user_id'], 'first_name', old_val, new_val)
            changes.append(('İsim', old_val, new_val))

        # Soyisim
        if existing['last_name'] != p['last_name']:
            old_val = existing['last_name'] or '(yok)'
            new_val = p['last_name'] or '(yok)'
            save_change(p['user_id'], 'last_name', old_val, new_val)
            changes.append(('Soyisim', old_val, new_val))

        if changes:
            save_member(p['user_id'], p['username'], p['first_name'], p['last_name'])
            changes_detected.append({
                'user_id': p['user_id'],
                'username': p['username'],
                'first_name': p['first_name'],
                'last_name': p['last_name'],
                'changes': changes
            })

    logger.info(f"Tamamlandı: {new_members} yeni, {len(changes_detected)} değişiklik")

    if changes_detected:
        await send_notifications(changes_detected)

async def send_notifications(changes_detected: list):
    """Gruba bildirim gönder"""
    batch_size = 10

    for i in range(0, len(changes_detected), batch_size):
        batch = changes_detected[i:i+batch_size]

        message = "🔄 **Değişiklik Tespit Edildi**\n\n"

        for change in batch:
            name = f"{change['first_name']} {change['last_name']}".strip()
            user_link = f"[{name}](tg://user?id={change['user_id']})"

            if change['username']:
                user_link += f" (@{change['username']})"

            message += f"👤 {user_link}\n"

            for ctype, old_v, new_v in change['changes']:
                message += f"   • {ctype}: `{old_v}` → `{new_v}`\n"

            message += "\n"

        try:
            await client.send_message(
                NOTIFICATION_GROUP_ID,
                message,
                parse_mode='markdown',
                link_preview=False
            )
        except Exception as e:
            logger.error(f"Bildirim hatası: {e}")

        await asyncio.sleep(2)

# ==================== KOMUTLAR ====================

@client.on(events.NewMessage(pattern='/kontrol'))
async def cmd_kontrol(event):
    """Manuel kontrol"""
    if event.sender_id not in get_admin_ids():
        return

    msg = await event.reply("🔍 Kontrol başlatılıyor...")
    await check_for_changes()
    await msg.edit("✅ Kontrol tamamlandı!")

@client.on(events.NewMessage(pattern='/istatistik'))
async def cmd_istatistik(event):
    """İstatistikler"""
    if event.sender_id not in get_admin_ids():
        return

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute('SELECT COUNT(*) as c FROM members')
    total = cur.fetchone()['c']

    cur.execute('SELECT COUNT(*) as c FROM changes')
    changes = cur.fetchone()['c']

    cur.execute('''
        SELECT COUNT(*) as c FROM changes
        WHERE changed_at > CURRENT_TIMESTAMP - INTERVAL '24 hours'
    ''')
    today = cur.fetchone()['c']

    cur.close()
    conn.close()

    await event.reply(f"""📊 **İstatistikler**

👥 Kayıtlı Üye: `{total:,}`
📝 Toplam Değişiklik: `{changes:,}`
🕐 Son 24 Saat: `{today}` değişiklik
⏰ Kontrol Aralığı: `{CHECK_INTERVAL_MINUTES}` dk""", parse_mode='markdown')

@client.on(events.NewMessage(pattern='/ara'))
async def cmd_ara(event):
    """Üye ara"""
    if event.sender_id not in get_admin_ids():
        return

    try:
        query = event.text.split(' ', 1)[1].strip()
    except:
        await event.reply("Kullanım: `/ara isim`", parse_mode='markdown')
        return

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute('''
        SELECT * FROM members
        WHERE username ILIKE %s OR first_name ILIKE %s OR last_name ILIKE %s
        LIMIT 15
    ''', (f'%{query}%', f'%{query}%', f'%{query}%'))

    results = cur.fetchall()
    cur.close()
    conn.close()

    if not results:
        await event.reply("❌ Sonuç yok")
        return

    msg = f"🔍 **Sonuçlar:** `{query}`\n\n"
    for r in results:
        name = f"{r['first_name']} {r['last_name']}".strip()
        msg += f"• [{name}](tg://user?id={r['user_id']})"
        if r['username']:
            msg += f" @{r['username']}"
        msg += "\n"

    await event.reply(msg, parse_mode='markdown')

@client.on(events.NewMessage(pattern='/gecmis'))
async def cmd_gecmis(event):
    """Üye geçmişi"""
    if event.sender_id not in get_admin_ids():
        return

    try:
        query = event.text.split(' ', 1)[1].strip()
    except:
        await event.reply("Kullanım: `/gecmis kullaniciadi`", parse_mode='markdown')
        return

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute('''
        SELECT * FROM members
        WHERE username ILIKE %s OR first_name ILIKE %s
        LIMIT 1
    ''', (f'%{query}%', f'%{query}%'))

    member = cur.fetchone()

    if not member:
        await event.reply("❌ Üye bulunamadı")
        cur.close()
        conn.close()
        return

    cur.execute('''
        SELECT * FROM changes WHERE user_id = %s
        ORDER BY changed_at DESC LIMIT 20
    ''', (member['user_id'],))

    history = cur.fetchall()
    cur.close()
    conn.close()

    name = f"{member['first_name']} {member['last_name']}".strip()

    if not history:
        await event.reply(f"ℹ️ `{name}` için geçmiş yok", parse_mode='markdown')
        return

    msg = f"📜 **{name}** Geçmişi\n"
    if member['username']:
        msg += f"@{member['username']}\n"
    msg += "\n"

    types = {'username': 'K.Adı', 'first_name': 'İsim', 'last_name': 'Soyisim'}

    for h in history:
        date = h['changed_at'].strftime('%d.%m.%Y %H:%M')
        t = types.get(h['change_type'], h['change_type'])
        msg += f"`{date}` {t}: `{h['old_value']}` → `{h['new_value']}`\n"

    await event.reply(msg, parse_mode='markdown')

@client.on(events.NewMessage(pattern='/yardim'))
async def cmd_yardim(event):
    """Yardım"""
    if event.sender_id not in get_admin_ids():
        return

    await event.reply("""📖 **Bot Komutları**

`/kontrol` - Manuel kontrol başlat
`/istatistik` - İstatistikleri göster
`/ara <isim>` - Üye ara
`/gecmis <isim>` - Üye geçmişi
`/yardim` - Bu mesaj""", parse_mode='markdown')

# ==================== ANA FONKSİYON ====================

async def main():
    logger.info("Bot başlatılıyor...")

    init_database()

    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş yapıldı: {me.first_name} (@{me.username})")

    # Scheduler
    scheduler = AsyncIOScheduler()
    scheduler.add_job(
        check_for_changes,
        'interval',
        minutes=CHECK_INTERVAL_MINUTES,
        id='check'
    )
    scheduler.start()
    logger.info(f"Zamanlayıcı: Her {CHECK_INTERVAL_MINUTES} dakikada kontrol")

    # İlk kontrol
    await check_for_changes()

    logger.info("Bot hazır!")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
