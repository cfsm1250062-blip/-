# -enetokuについて
コレはekotokuのやつです。

//データ例(OCR後)
{
  "type": "electricity",
  "month": "2025-01",
  "usage_kwh": 320,
  "price_yen": 9800
}

//Pythonバックエンド（FastAPI例）
pip install fastapi uvicorn pillow pytesseract pandas

//OCR処理
from PIL import Image
import pytesseract

def extract_text_from_image(image_path: str) -> str:
    img = Image.open(image_path)
    text = pytesseract.image_to_string(img, lang="jpn")
    return text

    金額・使用量の抽出（超シンプル版）
    import re

def parse_bill(text: str):
    price = re.search(r'([0-9,]+)円', text)
    usage = re.search(r'([0-9]+)kWh', text)

    return {
        "price_yen": int(price.group(1).replace(",", "")) if price else None,
        "usage_kwh": int(usage.group(1)) if usage else None
    }

//節約アドバイスAI（ルールベース）
def generate_advice(current, past_average):
    advice = []

    if current["usage_kwh"] > past_average["usage_kwh"] * 1.1:
        advice.append("電力使用量が平均より多めです。エアコンの設定温度を1℃見直してみましょう。")

    if current["price_yen"] > past_average["price_yen"]:
        advice.append("料金が上昇しています。電力会社のプラン見直しがおすすめです。")

    if not advice:
        advice.append("今月は順調です。この調子をキープしましょう！")

    return advice

//FastAPIエンドポイント
from fastapi import FastAPI, UploadFile
import shutil

app = FastAPI()

@app.post("/analyze")
async def analyze_bill(file: UploadFile):
    path = f"tmp/{file.filename}"
    with open(path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)

    text = extract_text_from_image(path)
    bill = parse_bill(text)

    past_average = {
        "usage_kwh": 280,
        "price_yen": 8500
    }

    advice = generate_advice(bill, past_average)

    return {
        "bill": bill,
        "advice": advice
    }
