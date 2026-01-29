# -enetokuについて
コレはekotokuのやつです。

[スマホアプリ]
  └ 写真撮影・アップロード
        ↓
[Backend API (FastAPI)]
  ├ OCR（請求額・使用量を抽出）
  ├ データ保存
  └ AI分析ロジック
        ↓
[節約アドバイスを返す]

backend/
├ main.py
├ ocr.py
├ analyzer.py
├ models.py
└ bills.db

//FastAPI（main.py）
from fastapi import FastAPI, File, UploadFile
from ocr import extract_bill_data
from analyzer import analyze_usage

app = FastAPI()

@app.post("/upload")
async def upload_bill(file: UploadFile = File(...)):
    image_bytes = await file.read()

    bill_data = extract_bill_data(image_bytes)
    advice = analyze_usage(bill_data)

    return {
        "bill_data": bill_data,
        "advice": advice
    }

import pytesseract
from PIL import Image
import io
import re

def extract_bill_data(image_bytes: bytes):
    image = Image.open(io.BytesIO(image_bytes))
    text = pytesseract.image_to_string(image, lang="jpn")

    # 金額抽出（例：¥3,245）
    amount_match = re.search(r'([0-9,]+)\s*円', text)
    amount = int(amount_match.group(1).replace(",", "")) if amount_match else None

    # 使用量抽出（例：123kWh / 15㎥）
    usage_match = re.search(r'([0-9]+)\s*(kWh|㎥)', text)
    usage = int(usage_match.group(1)) if usage_match else None

    return {
        "raw_text": text,
        "amount_yen": amount,
        "usage": usage
    }

def analyze_usage(bill_data):
    advice = []

    amount = bill_data.get("amount_yen")
    usage = bill_data.get("usage")

    if amount is None:
        return ["請求金額を読み取れませんでした。"]

    if amount > 8000:
        advice.append("先月より使用量が多い可能性があります。")

    if usage:
        if usage > 300:
            advice.append("使用量が多めです。夜間電力や節水シャワーを検討してみてください。")
        else:
            advice.append("使用量は平均的です。この調子を維持しましょう。")

    advice.append("家電の待機電力を減らすと月5〜10%節約できる可能性があります。")

    return advice

import sqlite3

def save_bill(amount, usage):
    conn = sqlite3.connect("bills.db")
    cur = conn.cursor()
    cur.execute("""
        CREATE TABLE IF NOT EXISTS bills (
            id INTEGER PRIMARY KEY,
            amount INTEGER,
            usage INTEGER
        )
    """)
    cur.execute("INSERT INTO bills (amount, usage) VALUES (?, ?)", (amount, usage))
    conn.commit()
    conn.close()
