"""
SangMata Entegrasyonlu Telegram Kullanıcı Geçmişi Botu
- Gruba gelen katılım isteklerini takip eder
- İstekleri SangMata'ya sorgular
"""

import os
import asyncio
import logging
from telethon import TelegramClient, events
from telethon.sessions import StringSession
from telethon.tl.types import PeerUser, PeerChannel, InputUserEmpty
from telethon.tl.functions.messages import GetChatInviteImportersRequest
import re

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

API_ID = int(os.getenv('API_ID', '0'))
API_HASH = os.getenv('API_HASH', '')
SESSION_STRING = os.getenv('SESSION_STRING', '')
GROUP_ID = int(os.getenv('GROUP_ID', '0'))
NOTIFICATION_GROUP_ID = int(os.getenv('NOTIFICATION_GROUP_ID', '0'))
ADMIN_IDS = os.getenv('ADMIN_IDS', '')

SANGMATA_BOT = '@sangMata_BOT'
pending_queries = {}
checked_requests = set()  # Zaten kontrol edilen istekler
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

# Katılım isteklerini kontrol et
group_name = "Grup"

async def check_join_requests():
    global bot_active, group_name

    await asyncio.sleep(2)

    try:
        chat = await client.get_entity(GROUP_ID)
        group_name = getattr(chat, 'title', 'Grup')
        logger.info(f"İstek kontrolü başladı: {group_name}")
    except Exception as e:
        logger.error(f"Grup erişim hatası: {e}")
        return

    while True:
        if bot_active:
            try:
                result = await client(GetChatInviteImportersRequest(
                    peer=GROUP_ID,
                    requested=True,
                    limit=50,
                    offset_date=None,
                    offset_user=InputUserEmpty(),
                    q=""
                ))

                count = result.count if hasattr(result, 'count') else len(result.importers)
                if count > 0:
                    logger.info(f"Bekleyen istek: {count}")

                for importer in result.importers:
                    user_id = importer.user_id

                    if user_id in checked_requests:
                        continue

                    checked_requests.add(user_id)

                    try:
                        user = await client.get_entity(user_id)
                        user_name = f"{user.first_name or ''} {user.last_name or ''}".strip()
                    except:
                        user_name = ""

                    logger.info(f"YENİ İSTEK: {user_name} ({user_id})")
                    await send_to_sangmata(user_id, group_name, user_name)

            except Exception as e:
                err = str(e)
                if "CHAT_ADMIN_REQUIRED" in err:
                    logger.error("Admin yetkisi gerekli!")
                elif "PEER" not in err:
                    logger.error(f"Hata: {e}")

        await asyncio.sleep(2)

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

        if text == '/temizle':
            checked_requests.clear()
            await event.reply("🗑 İstek geçmişi temizlendi")
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

    try:
        chat = await client.get_entity(GROUP_ID)
        logger.info(f"Grup bulundu: {chat.title}")
    except Exception as e:
        logger.error(f"GRUP BULUNAMADI: {e}")

    # İstek kontrolünü başlat
    asyncio.create_task(check_join_requests())

    logger.info("Bot hazır! Katılım istekleri kontrol ediliyor...")
    await client.run_until_disconnected()

if __name__ == '__main__':
    asyncio.run(main())
