# pip install opencv-python
# pip install pytesseract
# pip install passporteye
# pip install numpy
# pip install pillow
# sudo apt install tesseract-ocr


import cv2
import pytesseract as ract
from passporteye import read_mrz
import numpy as np
import base64
import argparse
import json
import tempfile
import os
import sys

def preprocessed_photo(photo_path):
    photo = cv2.imread(photo_path)
    photo = cv2.resize(photo, None, fx=2, fy=2, interpolation=cv2.INTER_CUBIC)
    gray = cv2.cvtColor(photo, cv2.COLOR_BGR2GRAY)
    gray = cv2.fastNlMeansDenoising(gray, h=31)
    thresh = cv2.adaptiveThreshold(
        gray, 254, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY, 30, 14
    )
    cv2.imwrite(photo_path, thresh)
    return photo_path

def mrz_score(data_dict):
    necessary_fields = [
        'surname', 'names', 'number', 'date_of_birth', 'expiration_date',
        'nationality', 'sex', 'personal_number'
    ]
    correct = sum(1 for field in necessary_fields
                  if data_dict.get(field, "") and data_dict[field] != "<<<<<<<<<<<<<<<<")
    return int((correct / len(necessary_fields)) * 100)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--image_base64', required=True)
    parser.add_argument('--tesseract_path', default='/usr/bin/tesseract')
    args = parser.parse_args()

    ract.pytesseract.tesseract_cmd = args.tesseract_path

    try:
        image_data = base64.b64decode(args.image_base64)
        np_arr = np.frombuffer(image_data, np.uint8)
        image = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

        with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as temp_file:
            cv2.imwrite(temp_file.name, image)
            preprocessed_path = preprocessed_photo(temp_file.name)

        mrz = read_mrz(preprocessed_path)
        os.remove(preprocessed_path)

        if mrz is None:
            result = {"success": False, "message": "No MRZ found"}
        else:
            data = mrz.to_dict()
            score = mrz_score(data)
            result = {
                "success": True,
                "parsed_data": data,
                "score": score,
                "valid_score": mrz.valid_score,
                "valid_mrz": mrz.valid_score and mrz.valid_score > 50
            }

    except Exception as e:
        result = {"success": False, "error": str(e)}

    print(json.dumps(result))

if __name__ == "__main__":
    main()