"""
SangMata Entegrasyonlu Telegram Kullanıcı Geçmişi Botu
- Gruba katılan kullanıcıların ID'sini @sangMata_BOT'a gönderir
- Gelen cevabı BİLDİRİM GRUBUNA iletir
- Tüm komutlar bildirim grubundan verilir
- /dur ve /başlat komutlarıyla botu kontrol
"""

import os
import asyncio
import logging
from telethon import TelegramClient, events
from telethon.sessions import StringSession
from telethon.tl.types import PeerUser, User
from telethon.tl.functions.users import GetFullUserRequest
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
NOTIFICATION_GROUP_ID = int(os.getenv('NOTIFICATION_GROUP_ID', '0'))
ADMIN_IDS = os.getenv('ADMIN_IDS', '')

# SangMata Bot
SANGMATA_BOT = '@sangMata_BOT'

# Bot durumu
bot_active = False
# Bekleyen sorgular: {user_id: {"source_chat": ..., "source_chat_name": ..., "user_name": ..., "timestamp": ...}}
pending_queries = {}

def get_admin_ids() -> list:
    """Admin ID'lerini al"""
    if not ADMIN_IDS:
        return []
    return [int(x.strip()) for x in ADMIN_IDS.split(',') if x.strip()]

# ==================== TELEGRAM CLIENT ====================

client = TelegramClient(StringSession(SESSION_STRING), API_ID, API_HASH)

async def send_to_sangmata(user_id: int, source_chat_id: int = None, source_chat_name: str = None, user_name: str = None):
    """SangMata'ya kullanıcı ID'si gönder"""
    try:
        # Pending query kaydet
        pending_queries[user_id] = {
            "source_chat": source_chat_id,
            "source_chat_name": source_chat_name or "Bilinmiyor",
            "user_name": user_name or "Bilinmiyor",
            "timestamp": asyncio.get_event_loop().time()
        }

        # SangMata'ya ID gönder
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

# ==================== EVENT HANDLERS ====================

@client.on(events.ChatAction())
async def on_chat_action(event):
    """Gruba katılan kullanıcıları takip et"""
    global bot_active

    try:
        # Bot aktif değilse çalışma
        if not bot_active:
            return

        # Bildirim grubunu atla
        if event.chat_id == NOTIFICATION_GROUP_ID:
            return

        # Yeni üye katıldıysa
        if event.user_joined or event.user_added:
            user = await event.get_user()
            if user and not user.bot:
                user_id = user.id
                user_name = f"{user.first_name or ''} {user.last_name or ''}".strip()

                # Grup bilgisini al
                try:
                    chat = await event.get_chat()
                    chat_name = getattr(chat, 'title', 'Bilinmeyen Grup')
                except:
                    chat_name = "Bilinmeyen Grup"

                logger.info(f"Yeni üye: {user_name} ({user_id}) - Grup: {chat_name}")

                # SangMata'ya gönder
                await send_to_sangmata(user_id, event.chat_id, chat_name, user_name)

    except Exception as e:
        logger.error(f"Chat action hatası: {e}")

@client.on(events.NewMessage(from_users=SANGMATA_BOT))
async def on_sangmata_response(event):
    """SangMata'dan gelen cevapları işle"""
    try:
        message = event.message
        text = message.text or ""

        # Mesajdan user_id çıkar (örn: "8179834359 için geçmiş")
        match = re.search(r'(\d{5,15})\s+için geçmiş', text)
        if not match:
            return

        user_id = int(match.group(1))

        # Pending query'den bilgileri al
        query_info = pending_queries.pop(user_id, None)

        if query_info:
            source_chat_name = query_info.get("source_chat_name", "")
            user_name = query_info.get("user_name", "")

            # Bildirim grubuna gönder
            header = ""
            if source_chat_name and source_chat_name != "Manuel Sorgu":
                header = f"📍 **Grup:** {source_chat_name}\n👤 **Üye:** {user_name}\n\n"

            await client.send_message(
                NOTIFICATION_GROUP_ID,
                f"{header}{text}",
                parse_mode='markdown'
            )
            logger.info(f"Cevap iletildi: {user_id}")
        else:
            # Pending'de yoksa bile bildirim grubuna gönder
            await client.send_message(
                NOTIFICATION_GROUP_ID,
                text,
                parse_mode='markdown'
            )

    except Exception as e:
        logger.error(f"SangMata cevap işleme hatası: {e}")

# ==================== KOMUTLAR (BİLDİRİM GRUBUNDA) ====================

@client.on(events.NewMessage(pattern=r'^/başlat$|^/baslat$'))
async def cmd_baslat(event):
    """Botu aktifleştir"""
    global bot_active

    try:
        # Sadece bildirim grubunda
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        # Admin kontrolü
        if event.sender_id not in get_admin_ids():
            return

        bot_active = True

        await event.reply(
            "✅ **Bot Aktifleştirildi!**\n\n"
            "• Tüm gruplara katılan üyeler sorgulanacak\n"
            "• Sonuçlar bu gruba gelecek\n"
            "• ID veya @kullaniciadi yazarak sorgulama yapabilirsiniz\n"
            "• Durdurmak için `/dur` yazın",
            parse_mode='markdown'
        )
        logger.info("Bot aktifleştirildi")

    except Exception as e:
        logger.error(f"Başlat komutu hatası: {e}")

@client.on(events.NewMessage(pattern=r'^/dur$'))
async def cmd_dur(event):
    """Botu durdur"""
    global bot_active

    try:
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        if event.sender_id not in get_admin_ids():
            return

        bot_active = False

        await event.reply(
            "⏹️ **Bot Durduruldu!**\n\n"
            "Tekrar başlatmak için `/başlat` yazın",
            parse_mode='markdown'
        )
        logger.info("Bot durduruldu")

    except Exception as e:
        logger.error(f"Dur komutu hatası: {e}")

@client.on(events.NewMessage(pattern=r'^/durum$'))
async def cmd_durum(event):
    """Bot durumunu göster"""
    try:
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        if event.sender_id not in get_admin_ids():
            return

        status = "✅ Aktif" if bot_active else "⏹️ Durduruldu"
        pending_count = len(pending_queries)

        await event.reply(
            f"📊 **Bot Durumu**\n\n"
            f"• Durum: {status}\n"
            f"• Bekleyen sorgu: `{pending_count}`",
            parse_mode='markdown'
        )

    except Exception as e:
        logger.error(f"Durum komutu hatası: {e}")

@client.on(events.NewMessage(pattern=r'^/yardım$|^/yardim$'))
async def cmd_yardim(event):
    """Yardım mesajı"""
    try:
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        if event.sender_id not in get_admin_ids():
            return

        await event.reply(
            "📖 **Bot Komutları**\n\n"
            "`/başlat` - Botu aktifleştir\n"
            "`/dur` - Botu durdur\n"
            "`/durum` - Bot durumunu göster\n"
            "`/yardım` - Bu mesaj\n\n"
            "**Sorgulama:**\n"
            "• `123456789` - ID ile sorgula\n"
            "• `@kullaniciadi` - Kullanıcı adı ile sorgula\n\n"
            "**Otomatik:**\n"
            "Bot aktifken herhangi bir gruba katılan üyelerin\n"
            "geçmişi otomatik sorgulanıp buraya gönderilir.",
            parse_mode='markdown'
        )

    except Exception as e:
        logger.error(f"Yardım komutu hatası: {e}")

# ==================== MANUEL SORGULAMA ====================

@client.on(events.NewMessage())
async def on_message(event):
    """Manuel ID veya kullanıcı adı sorgulaması"""
    try:
        # Sadece bildirim grubunda
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        # Admin kontrolü
        if event.sender_id not in get_admin_ids():
            return

        text = (event.text or "").strip()

        # Komutları atla
        if text.startswith('/'):
            return

        # Sadece ID (sayı)
        if re.match(r'^\d{5,15}$', text):
            user_id = int(text)
            logger.info(f"Manuel ID sorgusu: {user_id}")

            # Bilgi mesajı
            info_msg = await event.reply(f"🔍 `{user_id}` sorgulanıyor...", parse_mode='markdown')

            # SangMata'ya gönder
            await send_to_sangmata(user_id, None, "Manuel Sorgu", f"ID: {user_id}")

            # Bilgi mesajını sil
            await asyncio.sleep(2)
            try:
                await info_msg.delete()
            except:
                pass

            return

        # Kullanıcı adı (@username)
        if text.startswith('@') and len(text) > 1:
            username = text[1:]

            # Geçerli kullanıcı adı kontrolü
            if not re.match(r'^[a-zA-Z][a-zA-Z0-9_]{4,31}$', username):
                return

            logger.info(f"Manuel kullanıcı adı sorgusu: @{username}")

            # Bilgi mesajı
            info_msg = await event.reply(f"🔍 `@{username}` sorgulanıyor...", parse_mode='markdown')

            # Kullanıcı adından ID al
            user_id, user_name = await get_user_id_from_username(username)

            if user_id:
                await send_to_sangmata(user_id, None, "Manuel Sorgu", f"@{username}")
            else:
                await event.reply(f"❌ `@{username}` bulunamadı!", parse_mode='markdown')

            # Bilgi mesajını sil
            await asyncio.sleep(2)
            try:
                await info_msg.delete()
            except:
                pass

            return

    except Exception as e:
        logger.error(f"Manuel sorgulama hatası: {e}")

# ==================== ESKİ SORGULARI TEMİZLE ====================

async def cleanup_old_queries():
    """5 dakikadan eski sorguları temizle"""
    while True:
        try:
            current_time = asyncio.get_event_loop().time()
            timeout = 300  # 5 dakika

            to_remove = [
                uid for uid, info in pending_queries.items()
                if current_time - info["timestamp"] > timeout
            ]
            for uid in to_remove:
                pending_queries.pop(uid, None)
                logger.info(f"Eski sorgu temizlendi: {uid}")

        except Exception as e:
            logger.error(f"Temizleme hatası: {e}")

        await asyncio.sleep(60)

# ==================== ANA FONKSİYON ====================

async def main():
    logger.info("Bot başlatılıyor...")

    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş yapıldı: {me.first_name} (@{me.username})")
    logger.info(f"Admin ID'leri: {get_admin_ids()}")
    logger.info(f"Bildirim Grubu: {NOTIFICATION_GROUP_ID}")

    # Eski sorguları temizleme görevi başlat
    asyncio.create_task(cleanup_old_queries())

    # Başlangıç mesajı
    try:
        await client.send_message(
            NOTIFICATION_GROUP_ID,
            "🤖 **Bot Başlatıldı!**\n\n"
            "Aktifleştirmek için `/başlat` yazın.",
            parse_mode='markdown'
        )
    except Exception as e:
        logger.error(f"Başlangıç mesajı gönderilemedi: {e}")

    logger.info("Bot hazır!")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
