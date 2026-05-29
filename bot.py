"""
SangMata Entegrasyonlu Telegram Kullanıcı Geçmişi Botu
- Gruba katılan kullanıcıların ID'sini @sangMata_BOT'a gönderir
- Gelen cevabı gruba iletir
- /dur ve /başlat komutlarıyla grup bazında kontrol
- ID veya kullanıcı adı ile manuel sorgulama
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
ADMIN_IDS = os.getenv('ADMIN_IDS', '')

# SangMata Bot
SANGMATA_BOT = '@sangMata_BOT'

# Aktif gruplar (bellekte tutulur)
active_groups = set()
# Bekleyen sorgular: {user_id: {"chat_id": ..., "timestamp": ...}}
pending_queries = {}
# Bekleyen manuel sorgular: {query_user_id: {"chat_id": ..., "original_user_id": ...}}
pending_manual_queries = {}

def get_admin_ids() -> list:
    """Admin ID'lerini al"""
    if not ADMIN_IDS:
        return []
    return [int(x.strip()) for x in ADMIN_IDS.split(',') if x.strip()]

# ==================== TELEGRAM CLIENT ====================

client = TelegramClient(StringSession(SESSION_STRING), API_ID, API_HASH)

async def send_to_sangmata(user_id: int, chat_id: int, manual: bool = False, original_sender: int = None):
    """SangMata'ya kullanıcı ID'si gönder"""
    try:
        # Pending query kaydet
        if manual and original_sender:
            pending_manual_queries[user_id] = {
                "chat_id": chat_id,
                "original_sender": original_sender,
                "timestamp": asyncio.get_event_loop().time()
            }
        else:
            pending_queries[user_id] = {
                "chat_id": chat_id,
                "timestamp": asyncio.get_event_loop().time()
            }

        # SangMata'ya ID gönder
        await client.send_message(SANGMATA_BOT, str(user_id))
        logger.info(f"SangMata'ya gönderildi: {user_id} (Grup: {chat_id})")

    except Exception as e:
        logger.error(f"SangMata'ya gönderme hatası: {e}")
        # Hata durumunda pending'den kaldır
        pending_queries.pop(user_id, None)
        pending_manual_queries.pop(user_id, None)

async def get_user_id_from_username(username: str) -> int:
    """Kullanıcı adından ID al"""
    try:
        # @ işaretini kaldır
        username = username.lstrip('@')
        user = await client.get_entity(username)
        return user.id
    except Exception as e:
        logger.error(f"Kullanıcı adı çözümleme hatası: {e}")
        return None

# ==================== EVENT HANDLERS ====================

@client.on(events.ChatAction())
async def on_chat_action(event):
    """Gruba katılan kullanıcıları takip et"""
    try:
        # Sadece aktif gruplarda çalış
        chat_id = event.chat_id
        if chat_id not in active_groups:
            return

        # Yeni üye katıldıysa
        if event.user_joined or event.user_added:
            user = await event.get_user()
            if user and not user.bot:
                user_id = user.id
                logger.info(f"Yeni üye katıldı: {user_id} (Grup: {chat_id})")

                # SangMata'ya gönder
                await send_to_sangmata(user_id, chat_id)

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

        # Önce manuel sorgulardan kontrol et
        if user_id in pending_manual_queries:
            query_info = pending_manual_queries.pop(user_id)
            chat_id = query_info["chat_id"]

            # Cevabı gruba ilet
            await client.send_message(
                chat_id,
                f"🔍 **Sorgulama Sonucu**\n\n{text}",
                parse_mode='markdown'
            )
            logger.info(f"Manuel sorgu cevabı iletildi: {user_id} -> {chat_id}")

        # Otomatik sorgular (yeni üye katılımı)
        elif user_id in pending_queries:
            query_info = pending_queries.pop(user_id)
            chat_id = query_info["chat_id"]

            # Cevabı gruba ilet
            await client.send_message(
                chat_id,
                f"👋 **Yeni Üye Geçmişi**\n\n{text}",
                parse_mode='markdown'
            )
            logger.info(f"Otomatik sorgu cevabı iletildi: {user_id} -> {chat_id}")

    except Exception as e:
        logger.error(f"SangMata cevap işleme hatası: {e}")

# ==================== KOMUTLAR ====================

@client.on(events.NewMessage(pattern=r'^/başlat$|^/baslat$'))
async def cmd_baslat(event):
    """Botu grupta aktifleştir"""
    try:
        # Sadece grup/süpergrup
        if not event.is_group:
            return

        # Admin kontrolü
        if event.sender_id not in get_admin_ids():
            return

        chat_id = event.chat_id
        active_groups.add(chat_id)

        await event.reply(
            "✅ **Bot Aktifleştirildi!**\n\n"
            "• Gruba katılan üyelerin geçmişi sorgulanacak\n"
            "• Kullanıcı ID veya @kullaniciadi yazarak sorgulama yapabilirsiniz\n"
            "• Durdurmak için `/dur` yazın",
            parse_mode='markdown'
        )
        logger.info(f"Bot aktifleştirildi: {chat_id}")

    except Exception as e:
        logger.error(f"Başlat komutu hatası: {e}")

@client.on(events.NewMessage(pattern=r'^/dur$'))
async def cmd_dur(event):
    """Botu grupta durdur"""
    try:
        if not event.is_group:
            return

        if event.sender_id not in get_admin_ids():
            return

        chat_id = event.chat_id
        active_groups.discard(chat_id)

        await event.reply(
            "⏹️ **Bot Durduruldu!**\n\n"
            "Tekrar başlatmak için `/başlat` yazın",
            parse_mode='markdown'
        )
        logger.info(f"Bot durduruldu: {chat_id}")

    except Exception as e:
        logger.error(f"Dur komutu hatası: {e}")

@client.on(events.NewMessage(pattern=r'^/durum$'))
async def cmd_durum(event):
    """Bot durumunu göster"""
    try:
        if not event.is_group:
            return

        if event.sender_id not in get_admin_ids():
            return

        chat_id = event.chat_id
        is_active = chat_id in active_groups

        status = "✅ Aktif" if is_active else "⏹️ Durduruldu"
        pending_count = len(pending_queries) + len(pending_manual_queries)

        await event.reply(
            f"📊 **Bot Durumu**\n\n"
            f"• Bu grupta: {status}\n"
            f"• Aktif grup sayısı: `{len(active_groups)}`\n"
            f"• Bekleyen sorgu: `{pending_count}`",
            parse_mode='markdown'
        )

    except Exception as e:
        logger.error(f"Durum komutu hatası: {e}")

@client.on(events.NewMessage(pattern=r'^/yardım$|^/yardim$'))
async def cmd_yardim(event):
    """Yardım mesajı"""
    try:
        if not event.is_group:
            return

        if event.sender_id not in get_admin_ids():
            return

        await event.reply(
            "📖 **Bot Komutları**\n\n"
            "`/başlat` - Botu bu grupta aktifleştir\n"
            "`/dur` - Botu bu grupta durdur\n"
            "`/durum` - Bot durumunu göster\n"
            "`/yardım` - Bu mesaj\n\n"
            "**Sorgulama:**\n"
            "• `123456789` - ID ile sorgula\n"
            "• `@kullaniciadi` - Kullanıcı adı ile sorgula\n\n"
            "Bot aktifken gruba katılan üyelerin geçmişi otomatik sorgulanır.",
            parse_mode='markdown'
        )

    except Exception as e:
        logger.error(f"Yardım komutu hatası: {e}")

# ==================== MANUEL SORGULAMA ====================

@client.on(events.NewMessage())
async def on_message(event):
    """Manuel ID veya kullanıcı adı sorgulaması"""
    try:
        # Sadece aktif gruplarda
        if not event.is_group:
            return

        chat_id = event.chat_id
        if chat_id not in active_groups:
            return

        # Admin kontrolü
        if event.sender_id not in get_admin_ids():
            return

        text = (event.text or "").strip()

        # Komutları atla
        if text.startswith('/'):
            return

        # Sadece ID (10 haneli sayı)
        if re.match(r'^\d{5,15}$', text):
            user_id = int(text)
            logger.info(f"Manuel ID sorgusu: {user_id}")

            # Bilgi mesajı
            info_msg = await event.reply(f"🔍 `{user_id}` sorgulanıyor...", parse_mode='markdown')

            # SangMata'ya gönder
            await send_to_sangmata(user_id, chat_id, manual=True, original_sender=event.sender_id)

            # Bilgi mesajını sil (3 saniye sonra)
            await asyncio.sleep(3)
            try:
                await info_msg.delete()
            except:
                pass

            return

        # Kullanıcı adı (@username)
        if text.startswith('@') and len(text) > 1:
            username = text[1:]  # @ işaretini kaldır

            # Geçerli kullanıcı adı kontrolü
            if not re.match(r'^[a-zA-Z][a-zA-Z0-9_]{4,31}$', username):
                return

            logger.info(f"Manuel kullanıcı adı sorgusu: @{username}")

            # Bilgi mesajı
            info_msg = await event.reply(f"🔍 `@{username}` sorgulanıyor...", parse_mode='markdown')

            # Kullanıcı adından ID al
            user_id = await get_user_id_from_username(username)

            if user_id:
                # SangMata'ya gönder
                await send_to_sangmata(user_id, chat_id, manual=True, original_sender=event.sender_id)
            else:
                await event.reply(f"❌ `@{username}` bulunamadı!", parse_mode='markdown')

            # Bilgi mesajını sil
            await asyncio.sleep(3)
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

            # Pending queries
            to_remove = [
                uid for uid, info in pending_queries.items()
                if current_time - info["timestamp"] > timeout
            ]
            for uid in to_remove:
                pending_queries.pop(uid, None)
                logger.info(f"Eski sorgu temizlendi: {uid}")

            # Manual queries
            to_remove = [
                uid for uid, info in pending_manual_queries.items()
                if current_time - info["timestamp"] > timeout
            ]
            for uid in to_remove:
                pending_manual_queries.pop(uid, None)
                logger.info(f"Eski manuel sorgu temizlendi: {uid}")

        except Exception as e:
            logger.error(f"Temizleme hatası: {e}")

        await asyncio.sleep(60)  # Her 1 dakikada kontrol

# ==================== ANA FONKSİYON ====================

async def main():
    logger.info("Bot başlatılıyor...")

    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş yapıldı: {me.first_name} (@{me.username})")
    logger.info(f"Admin ID'leri: {get_admin_ids()}")

    # Eski sorguları temizleme görevi başlat
    asyncio.create_task(cleanup_old_queries())

    logger.info("Bot hazır! Gruplarda /başlat yazarak aktifleştirin.")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
