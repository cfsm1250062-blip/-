# -enetokuについて
コレはekotokuのやつです。

//データモデル例
class UtilityBill:
    date: str          # 2025-01
    utility_type: str  # electricity / gas / water
    usage: float       # kWh, m3, etc
    cost: float        # 円

   // ① OCR処理（画像 → テキスト）
   import pytesseract
from PIL import Image

def extract_text_from_image(image_path: str) -> str:
    image = Image.open(image_path)
    text = pytesseract.image_to_string(image, lang="jpn")
    return text

//② テキストから金額・使用量を抽出
import re

def parse_bill_text(text: str) -> dict:
    cost_match = re.search(r'([0-9,]+)円', text)
    usage_match = re.search(r'([0-9.]+)\s*(kWh|㎥|m3)', text)

    return {
        "cost": int(cost_match.group(1).replace(',', '')) if cost_match else None,
        "usage": float(usage_match.group(1)) if usage_match else None,
    }

//③ 基準値（ベースライン）計算
import pandas as pd

def calculate_baseline(bills: list[dict]):
    df = pd.DataFrame(bills)

    baseline = {
        "avg_cost": df["cost"].mean(),
        "avg_usage": df["usage"].mean(),
        "max_cost": df["cost"].max(),
        "min_cost": df["cost"].min(),
    }

    return baseline

//④ 節約判定ロジック
def check_saving_status(current_bill, baseline):
    messages = []

    if current_bill["cost"] > baseline["avg_cost"] * 1.1:
        messages.append("平均より10%以上高いです。使用量を見直しましょう。")

    if current_bill["usage"] > baseline["avg_usage"] * 1.1:
        messages.append("使用量が増加しています。待機電力や使いすぎに注意。")

    if not messages:
        messages.append("今月は節約できています！")

    return messages

//⑤ FastAPI（画像アップロードAPI）
from fastapi import FastAPI, UploadFile
import shutil

app = FastAPI()

@app.post("/upload")
async def upload_bill(file: UploadFile):
    file_path = f"tmp/{file.filename}"

    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)

    text = extract_text_from_image(file_path)
    bill_data = parse_bill_text(text)

    return {
        "extracted_text": text,
        "bill_data": bill_data
    }

//⑥ AI節約アドバイス（発展）
def generate_advice(bill, baseline):
    if bill["usage"] > baseline["avg_usage"]:
        return "エアコンの設定温度を1℃調整すると年間約10%節電できます。"
    return "この調子で続けましょう！"

