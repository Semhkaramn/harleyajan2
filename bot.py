"""
SangMata Entegrasyonlu Telegram Kullanıcı Geçmişi Botu
- Gruba katılan kullanıcıların ID'sini @sangMata_BOT'a gönderir
- Gelen cevabı BİLDİRİM GRUBUNA iletir
- ID veya @kullaniciadi ile manuel sorgulama
"""

import os
import asyncio
import logging
from telethon import TelegramClient, events
from telethon.sessions import StringSession
import re

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
NOTIFICATION_GROUP_ID = os.getenv('NOTIFICATION_GROUP_ID', '0')
ADMIN_IDS = os.getenv('ADMIN_IDS', '')

# NOTIFICATION_GROUP_ID'yi düzgün parse et
try:
    NOTIFICATION_GROUP_ID = int(NOTIFICATION_GROUP_ID)
except:
    NOTIFICATION_GROUP_ID = 0

# SangMata Bot
SANGMATA_BOT = '@sangMata_BOT'

# Bekleyen sorgular
pending_queries = {}

def get_admin_ids() -> list:
    """Admin ID'lerini al"""
    if not ADMIN_IDS:
        return []
    try:
        return [int(x.strip()) for x in ADMIN_IDS.split(',') if x.strip()]
    except:
        return []

# ==================== TELEGRAM CLIENT ====================

client = TelegramClient(StringSession(SESSION_STRING), API_ID, API_HASH)

async def send_to_sangmata(user_id: int, source_chat_id: int = None, source_chat_name: str = None, user_name: str = None):
    """SangMata'ya kullanıcı ID'si gönder"""
    try:
        pending_queries[user_id] = {
            "source_chat": source_chat_id,
            "source_chat_name": source_chat_name or "Bilinmiyor",
            "user_name": user_name or "Bilinmiyor",
            "timestamp": asyncio.get_event_loop().time()
        }

        await client.send_message(SANGMATA_BOT, str(user_id))
        logger.info(f"SangMata'ya gönderildi: {user_id}")

    except Exception as e:
        logger.error(f"SangMata'ya gönderme hatası: {e}")
        pending_queries.pop(user_id, None)

async def get_user_id_from_username(username: str) -> tuple:
    """Kullanıcı adından ID ve isim al"""
    try:
        username = username.lstrip('@')
        user = await client.get_entity(username)
        name = f"{user.first_name or ''} {user.last_name or ''}".strip() or username
        return user.id, name
    except Exception as e:
        logger.error(f"Kullanıcı adı çözümleme hatası: {e}")
        return None, None

# ==================== GRUBA KATILAN ÜYELERİ TAKİP ====================

@client.on(events.ChatAction())
async def on_chat_action(event):
    """Gruba katılan kullanıcıları takip et"""
    try:
        # Bildirim grubunu atla
        if event.chat_id == NOTIFICATION_GROUP_ID:
            return

        # Yeni üye katıldıysa
        if event.user_joined or event.user_added:
            user = await event.get_user()
            if user and not user.bot:
                user_id = user.id
                user_name = f"{user.first_name or ''} {user.last_name or ''}".strip()

                try:
                    chat = await event.get_chat()
                    chat_name = getattr(chat, 'title', 'Bilinmeyen Grup')
                except:
                    chat_name = "Bilinmeyen Grup"

                logger.info(f"Yeni üye: {user_name} ({user_id}) - Grup: {chat_name}")
                await send_to_sangmata(user_id, event.chat_id, chat_name, user_name)

    except Exception as e:
        logger.error(f"Chat action hatası: {e}")

# ==================== SANGMATA CEVAPLARINI AL ====================

@client.on(events.NewMessage(incoming=True))
async def on_sangmata_response(event):
    """SangMata'dan gelen cevapları işle"""
    try:
        # Sadece private mesajları kontrol et
        if not event.is_private:
            return

        # Göndereni kontrol et
        sender = await event.get_sender()
        if not sender:
            return

        # SangMata bot mu kontrol et
        sender_username = getattr(sender, 'username', '') or ''

        if sender_username.lower() != 'sangmata_bot':
            return

        text = event.message.text or ""
        if not text:
            return

        logger.info(f"SangMata'dan mesaj geldi: {text[:100]}")

        # Header oluştur
        header = ""
        match = re.search(r'(\d{5,15})\s+için geçmiş', text)
        if match:
            user_id = int(match.group(1))
            query_info = pending_queries.pop(user_id, None)

            if query_info:
                source_chat_name = query_info.get("source_chat_name", "")
                user_name = query_info.get("user_name", "")

                if source_chat_name and source_chat_name != "Manuel Sorgu":
                    header = f"📍 **Grup:** {source_chat_name}\n👤 **Üye:** {user_name}\n\n"

        # Bildirim grubuna gönder
        await client.send_message(
            NOTIFICATION_GROUP_ID,
            f"{header}{text}",
            parse_mode='markdown'
        )
        logger.info("SangMata cevabı bildirim grubuna gönderildi")

    except Exception as e:
        logger.error(f"SangMata cevap işleme hatası: {e}")

# ==================== MANUEL SORGULAMA ====================

@client.on(events.NewMessage())
async def on_manual_query(event):
    """Manuel ID, kullanıcı adı veya iletilen mesaj sorgulaması"""
    try:
        # Sadece bildirim grubunda
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        # Kendi mesajlarımızı atla
        me = await client.get_me()
        if event.sender_id == me.id:
            return

        # Admin kontrolü
        if event.sender_id not in get_admin_ids():
            return

        # İletilen mesaj kontrolü
        if event.message.fwd_from:
            fwd = event.message.fwd_from
            user_id = None

            # from_id'den user_id al
            if fwd.from_id:
                from telethon.tl.types import PeerUser
                if isinstance(fwd.from_id, PeerUser):
                    user_id = fwd.from_id.user_id

            if user_id:
                logger.info(f"İletilen mesajdan sorgu: {user_id}")
                await send_to_sangmata(user_id, None, "Manuel Sorgu", "İletilen mesaj")
            return

        text = (event.text or "").strip()

        # Komutları atla
        if text.startswith('/'):
            return

        # Sadece ID (sayı)
        if re.match(r'^\d{5,15}

# ==================== ESKİ SORGULARI TEMİZLE ====================

async def cleanup_old_queries():
    """5 dakikadan eski sorguları temizle"""
    while True:
        try:
            current_time = asyncio.get_event_loop().time()
            timeout = 300

            to_remove = [
                uid for uid, info in pending_queries.items()
                if current_time - info["timestamp"] > timeout
            ]
            for uid in to_remove:
                pending_queries.pop(uid, None)

        except Exception as e:
            logger.error(f"Temizleme hatası: {e}")

        await asyncio.sleep(60)

# ==================== ANA FONKSİYON ====================

async def main():
    logger.info("Bot başlatılıyor...")

    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş yapıldı: {me.first_name} (@{me.username}) - ID: {me.id}")

    asyncio.create_task(cleanup_old_queries())

    logger.info("Bot hazır ve çalışıyor!")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
, text):
            user_id = int(text)
            logger.info(f"Manuel ID sorgusu: {user_id}")
            await send_to_sangmata(user_id, None, "Manuel Sorgu", f"ID: {user_id}")
            return

        # Kullanıcı adı (@username)
        if text.startswith('@') and len(text) > 1:
            username = text[1:]

            if not re.match(r'^[a-zA-Z][a-zA-Z0-9_]{3,31}

# ==================== ESKİ SORGULARI TEMİZLE ====================

async def cleanup_old_queries():
    """5 dakikadan eski sorguları temizle"""
    while True:
        try:
            current_time = asyncio.get_event_loop().time()
            timeout = 300

            to_remove = [
                uid for uid, info in pending_queries.items()
                if current_time - info["timestamp"] > timeout
            ]
            for uid in to_remove:
                pending_queries.pop(uid, None)

        except Exception as e:
            logger.error(f"Temizleme hatası: {e}")

        await asyncio.sleep(60)

# ==================== ANA FONKSİYON ====================

async def main():
    logger.info("Bot başlatılıyor...")

    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş yapıldı: {me.first_name} (@{me.username}) - ID: {me.id}")

    asyncio.create_task(cleanup_old_queries())

    logger.info("Bot hazır ve çalışıyor!")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
, username):
                return

            logger.info(f"Manuel kullanıcı adı sorgusu: @{username}")

            user_id, user_name = await get_user_id_from_username(username)

            if user_id:
                await send_to_sangmata(user_id, None, "Manuel Sorgu", f"@{username}")
            return

    except Exception as e:
        logger.error(f"Manuel sorgulama hatası: {e}")

# ==================== ESKİ SORGULARI TEMİZLE ====================

async def cleanup_old_queries():
    """5 dakikadan eski sorguları temizle"""
    while True:
        try:
            current_time = asyncio.get_event_loop().time()
            timeout = 300

            to_remove = [
                uid for uid, info in pending_queries.items()
                if current_time - info["timestamp"] > timeout
            ]
            for uid in to_remove:
                pending_queries.pop(uid, None)

        except Exception as e:
            logger.error(f"Temizleme hatası: {e}")

        await asyncio.sleep(60)

# ==================== ANA FONKSİYON ====================

async def main():
    logger.info("Bot başlatılıyor...")

    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş yapıldı: {me.first_name} (@{me.username}) - ID: {me.id}")

    asyncio.create_task(cleanup_old_queries())

    logger.info("Bot hazır ve çalışıyor!")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
