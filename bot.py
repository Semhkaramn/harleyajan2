"""
SangMata Entegrasyonlu Telegram Kullanıcı Geçmişi Botu
"""

import os
import asyncio
import logging
from telethon import TelegramClient, events
from telethon.sessions import StringSession
from telethon.tl.types import (
    PeerUser,
    MessageActionChatJoinedByLink,
    MessageActionChatAddUser,
    MessageActionChatJoinedByRequest,
    MessageService,
    UpdateNewMessage,
    UpdateNewChannelMessage
)
from telethon import functions
import re

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

API_ID = int(os.getenv('API_ID', '0'))
API_HASH = os.getenv('API_HASH', '')
SESSION_STRING = os.getenv('SESSION_STRING', '')
GROUP_ID = int(os.getenv('GROUP_ID', '0'))  # Takip edilecek grup
NOTIFICATION_GROUP_ID = int(os.getenv('NOTIFICATION_GROUP_ID', '0'))  # Bildirimlerin gideceği grup
ADMIN_IDS = os.getenv('ADMIN_IDS', '')

SANGMATA_BOT = '@sangMata_BOT'
pending_queries = {}
bot_active = True

def get_admin_ids():
    if not ADMIN_IDS:
        return []
    try:
        return [int(x.strip()) for x in ADMIN_IDS.split(',') if x.strip()]
    except:
        return []

client = TelegramClient(StringSession(SESSION_STRING), API_ID, API_HASH)

async def send_to_sangmata(user_id, source_chat_name=None, user_name=None):
    try:
        pending_queries[user_id] = {
            "source_chat_name": source_chat_name or "",
            "user_name": user_name or "",
            "timestamp": asyncio.get_event_loop().time()
        }
        await client.send_message(SANGMATA_BOT, str(user_id))
        logger.info(f"SangMata'ya gönderildi: {user_id}")
    except Exception as e:
        logger.error(f"SangMata hatası: {e}")
        pending_queries.pop(user_id, None)

# Raw handler - TÜM mesajları yakala
@client.on(events.Raw())
async def on_raw(event):
    global bot_active

    try:
        # Sadece yeni mesaj güncellemelerini al
        if not isinstance(event, (UpdateNewMessage, UpdateNewChannelMessage)):
            return

        message = event.message

        # Sadece servis mesajlarını kontrol et
        if not isinstance(message, MessageService):
            return

        action = message.action

        # Chat ID al
        chat_id = None
        if hasattr(message, 'peer_id'):
            if hasattr(message.peer_id, 'channel_id'):
                chat_id = -1000000000000 - message.peer_id.channel_id
            elif hasattr(message.peer_id, 'chat_id'):
                chat_id = -message.peer_id.chat_id

        # TÜM servis mesajlarını logla
        logger.info(f"SERVICE: {type(action).__name__} | Chat: {chat_id} | GROUP_ID: {GROUP_ID}")

        # Katılım action'larını kontrol et
        if not isinstance(action, (MessageActionChatJoinedByLink, MessageActionChatAddUser, MessageActionChatJoinedByRequest)):
            return

        if not bot_active:
            return

        # Sadece GROUP_ID'yi takip et
        if chat_id != GROUP_ID:
            logger.info(f"Chat eşleşmedi, atlanıyor")
            return

        logger.info(f">>> KATILIM ALGILANDI! Chat: {chat_id}")

        # User ID'leri al
        user_ids = []

        if isinstance(action, MessageActionChatAddUser):
            user_ids = action.users
        elif isinstance(action, (MessageActionChatJoinedByLink, MessageActionChatJoinedByRequest)):
            if hasattr(message, 'from_id') and message.from_id:
                if isinstance(message.from_id, PeerUser):
                    user_ids = [message.from_id.user_id]
                elif hasattr(message.from_id, 'user_id'):
                    user_ids = [message.from_id.user_id]

        if not user_ids:
            return

        # Chat adını al
        try:
            chat = await client.get_entity(chat_id)
            chat_name = getattr(chat, 'title', 'Grup')
        except:
            chat_name = "Grup"

        # Her kullanıcı için sorgu yap
        for uid in user_ids:
            try:
                user = await client.get_entity(uid)
                if user.bot:
                    continue
                user_name = f"{user.first_name or ''} {user.last_name or ''}".strip()
            except:
                user_name = ""

            logger.info(f"Yeni üye: {user_name} ({uid}) - {chat_name}")
            await send_to_sangmata(uid, chat_name, user_name)

    except Exception as e:
        logger.error(f"Raw handler hatası: {e}")

# O gruptan gelen TÜM mesajları logla (test)
@client.on(events.NewMessage(chats=GROUP_ID))
async def on_group_message(event):
    logger.info(f"GRUP MESAJI: {event.chat_id} - {type(event.message).__name__}")

# ChatAction da deneyelim (yedek)
@client.on(events.ChatAction(chats=GROUP_ID))
async def on_chat_action(event):
    global bot_active

    if not bot_active:
        return

    try:
        # Sadece GROUP_ID'yi takip et
        if event.chat_id != GROUP_ID:
            return

        logger.info(f"ChatAction algılandı! Chat: {event.chat_id}")

        if not (event.user_joined or event.user_added):
            return

        user = await event.get_user()
        if not user or user.bot:
            return

        chat = await event.get_chat()
        chat_name = getattr(chat, 'title', 'Grup')
        user_name = f"{user.first_name or ''} {user.last_name or ''}".strip()

        logger.info(f"ChatAction - Yeni üye: {user_name} ({user.id}) - {chat_name}")
        await send_to_sangmata(user.id, chat_name, user_name)

    except Exception as e:
        logger.error(f"ChatAction hatası: {e}")

# SangMata cevapları
@client.on(events.NewMessage(incoming=True))
async def on_sangmata_response(event):
    try:
        if not event.is_private:
            return

        sender = await event.get_sender()
        if not sender or (getattr(sender, 'username', '') or '').lower() != 'sangmata_bot':
            return

        text = event.message.text or ""
        if not text:
            return

        logger.info("SangMata'dan cevap geldi")

        header = ""
        match = re.search(r'(\d{5,15})\s+için geçmiş', text)
        if match:
            uid = int(match.group(1))
            info = pending_queries.pop(uid, None)
            if info and info.get("source_chat_name") and info["source_chat_name"] != "Manuel Sorgu":
                header = f"📍 **Grup:** {info['source_chat_name']}\n👤 **Üye:** {info.get('user_name', '')}\n\n"

        await client.send_message(NOTIFICATION_GROUP_ID, f"{header}{text}", parse_mode='markdown')

    except Exception as e:
        logger.error(f"SangMata cevap hatası: {e}")

# Manuel sorgulama
@client.on(events.NewMessage())
async def on_manual_query(event):
    global bot_active

    try:
        if event.chat_id != NOTIFICATION_GROUP_ID:
            return

        me = await client.get_me()
        if event.sender_id == me.id:
            return

        if event.sender_id not in get_admin_ids():
            return

        # İletilen mesaj
        if event.message.fwd_from:
            fwd = event.message.fwd_from
            if fwd.from_id and isinstance(fwd.from_id, PeerUser):
                uid = fwd.from_id.user_id
                logger.info(f"İletilen mesajdan sorgu: {uid}")
                await send_to_sangmata(uid, "Manuel Sorgu", "")
            return

        text = (event.text or "").strip()

        if text == '/dur':
            bot_active = False
            await event.reply("⏸ Durduruldu")
            return

        if text == '/devam':
            bot_active = True
            await event.reply("▶️ Devam")
            return

        if text.startswith('/'):
            return

        # ID
        if re.match(r'^\d{5,15}$', text):
            await send_to_sangmata(int(text), "Manuel Sorgu", "")
            return

        # @username
        if text.startswith('@') and len(text) > 1:
            try:
                user = await client.get_entity(text)
                await send_to_sangmata(user.id, "Manuel Sorgu", "")
            except:
                pass
            return

    except Exception as e:
        logger.error(f"Manuel sorgu hatası: {e}")

async def main():
    logger.info("Bot başlatılıyor...")
    await client.start()
    me = await client.get_me()
    logger.info(f"Giriş: {me.first_name} (@{me.username}) ID: {me.id}")
    logger.info(f"Takip edilen grup: {GROUP_ID}")
    logger.info(f"Bildirim grubu: {NOTIFICATION_GROUP_ID}")

    # Gruba erişimi kontrol et
    try:
        chat = await client.get_entity(GROUP_ID)
        logger.info(f"Grup bulundu: {chat.title}")
    except Exception as e:
        logger.error(f"GRUP BULUNAMADI: {e}")

    logger.info("Bot hazır!")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
