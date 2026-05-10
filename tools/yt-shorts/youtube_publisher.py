"""
YouTube Shorts upload via YouTube Data API v3.

Setup:
  1. https://console.cloud.google.com/apis/credentials
  2. Abilita "YouTube Data API v3"
  3. Crea OAuth 2.0 Client ID (Desktop App)
  4. Scarica JSON come `client_secret.json` in questa cartella
  5. Esegui: python youtube_publisher.py --auth
  6. Browser → login → grant `youtube.upload`
  7. Token salvato in `youtube_token.pickle`

Una volta fatto setup, usa upload(...) per pubblicare un Short.
"""
from __future__ import annotations

import os
import pickle
from pathlib import Path

from google_auth_oauthlib.flow import InstalledAppFlow
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload
from googleapiclient.errors import HttpError


HERE = Path(__file__).resolve().parent
CLIENT_SECRET = HERE / "client_secret.json"
TOKEN_FILE = HERE / "youtube_token.pickle"

SCOPES = [
    "https://www.googleapis.com/auth/youtube.upload",
    "https://www.googleapis.com/auth/youtube",
]


def _load_credentials():
    creds = None
    if TOKEN_FILE.exists():
        with open(TOKEN_FILE, "rb") as f:
            creds = pickle.load(f)
    if creds and creds.expired and creds.refresh_token:
        creds.refresh(Request())
        with open(TOKEN_FILE, "wb") as f:
            pickle.dump(creds, f)
    return creds


def authorize() -> None:
    """Run OAuth flow and save the token. Call once."""
    if not CLIENT_SECRET.exists():
        raise FileNotFoundError(
            f"client_secret.json mancante in {HERE}. "
            "Vai su Google Cloud Console e scarica le OAuth credentials."
        )
    flow = InstalledAppFlow.from_client_secrets_file(str(CLIENT_SECRET), SCOPES)
    creds = flow.run_local_server(port=0)
    with open(TOKEN_FILE, "wb") as f:
        pickle.dump(creds, f)
    print(f"[auth] token salvato in {TOKEN_FILE}")


def _service():
    creds = _load_credentials()
    if not creds:
        raise RuntimeError("Token mancante. Esegui: python youtube_publisher.py --auth")
    return build("youtube", "v3", credentials=creds, cache_discovery=False)


def upload(
    video_path: Path,
    title: str,
    description: str,
    tags: list[str] | None = None,
    privacy: str = "unlisted",  # public | unlisted | private — default SAFE
    cover_path: Path | None = None,
    category_id: str = "22",   # 22 = People & Blogs
    made_for_kids: bool = False,
) -> str:
    """
    Upload a Short. Returns the YouTube video ID.

    For YouTube Shorts:
      - Vertical 9:16 video, <= 3 minutes
      - Hashtag #Shorts in title or description rinforza la classifica come Short
    """
    yt = _service()

    if "#shorts" not in (title.lower() + description.lower()):
        description = description.rstrip() + "\n\n#Shorts"

    body = {
        "snippet": {
            "title": title[:100],
            "description": description[:5000],
            "tags": (tags or [])[:30],
            "categoryId": category_id,
            "defaultLanguage": "it",
            "defaultAudioLanguage": "it",
        },
        "status": {
            "privacyStatus": privacy,
            "selfDeclaredMadeForKids": made_for_kids,
            "embeddable": True,
        },
    }

    media = MediaFileUpload(str(video_path), chunksize=-1, resumable=True, mimetype="video/mp4")
    print(f"[upload] uploading {video_path.name}...")
    request = yt.videos().insert(part="snippet,status", body=body, media_body=media)

    response = None
    while response is None:
        try:
            status, response = request.next_chunk()
            if status:
                print(f"  progress: {int(status.progress() * 100)}%")
        except HttpError as e:
            raise RuntimeError(f"YouTube upload error: {e}") from e

    video_id = response["id"]
    url = f"https://youtu.be/{video_id}"
    print(f"[upload] OK: {url}")

    # Cover (thumbnail)
    if cover_path and cover_path.exists():
        try:
            yt.thumbnails().set(videoId=video_id,
                                media_body=MediaFileUpload(str(cover_path), mimetype="image/png")).execute()
            print(f"[upload] thumbnail set from {cover_path.name}")
        except HttpError as e:
            # custom thumbnails require account verified for this feature
            print(f"[upload] thumbnail set FAILED ({e}). Per usare custom thumbnail: l'account YouTube deve essere verificato.")

    return video_id


if __name__ == "__main__":
    import sys
    if "--auth" in sys.argv:
        authorize()
    elif len(sys.argv) >= 4:
        vid_path = Path(sys.argv[1])
        title = sys.argv[2]
        desc = sys.argv[3]
        cover = Path(sys.argv[4]) if len(sys.argv) > 4 else None
        # CLI default = unlisted per sicurezza, mai public da CLI invocation
        upload(vid_path, title, desc, cover_path=cover, privacy="unlisted")
    else:
        print("usage:")
        print("  python youtube_publisher.py --auth")
        print("  python youtube_publisher.py <video.mp4> <title> <description> [cover.png]")
