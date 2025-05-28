from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import cv2
import pytesseract as ract
from passporteye import read_mrz
import numpy as np
import base64
import tempfile
import os
from datetime import datetime
import traceback
import re
import logging
import platform
import shutil

# Configure logging for clean terminal output
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler('passport_api.log')
    ]
)
logger = logging.getLogger(__name__)

# Run with: python -m uvicorn main:app --host 127.0.0.1 --port 8000
app = FastAPI()

# Auto-detect tesseract path for cross-platform compatibility
def setup_tesseract():
    """Configure tesseract path based on operating system"""
    if platform.system() == "Windows":
        tesseract_path = shutil.which("tesseract")
        if not tesseract_path:
            common_paths = [
                r"C:\Program Files\Tesseract-OCR\tesseract.exe",
                r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
                r"C:\tools\tesseract\tesseract.exe"
            ]
            for path in common_paths:
                if os.path.exists(path):
                    tesseract_path = path
                    break
        
        if tesseract_path:
            ract.pytesseract.tesseract_cmd = tesseract_path
            logger.info(f"Tesseract configured at: {tesseract_path}")
            return True
        else:
            logger.error("Tesseract not found on Windows")
            return False
    else:
        # Linux/Mac
        possible_paths = ['/usr/bin/tesseract', '/usr/local/bin/tesseract']
        for path in possible_paths:
            if os.path.exists(path):
                ract.pytesseract.tesseract_cmd = path
                logger.info(f"Tesseract configured at: {path}")
                return True
        logger.error("Tesseract not found on Unix system")
        return False

# Initialize tesseract on startup
tesseract_available = setup_tesseract()

class ImagePayload(BaseModel):
    """Request model for image processing"""
    image_base64: str
    preprocess: bool = False
    enhance_contrast: bool = False
    denoise: bool = False
    sharpen: bool = False

def normalize_mrz_line(line):
    """Normalize MRZ line format and length"""
    line = line.upper().replace('O', '0').replace('I', '1')
    return line.ljust(44, '<')[:44]

def is_mrz_line(line):
    """Check if a line matches MRZ format pattern"""
    return re.fullmatch(r"[A-Z0-9<]{40,45}", line) is not None

def extract_mrz_fallback(text):
    """Extract MRZ lines from raw OCR text as fallback method"""
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    mrz_candidates = [line for line in lines if is_mrz_line(line)]
    
    for i in range(len(mrz_candidates) - 1):
        line1, line2 = mrz_candidates[i], mrz_candidates[i + 1]
        if len(line1) >= 40 and len(line2) >= 40:
            return [normalize_mrz_line(line1), normalize_mrz_line(line2)]
    return None

def simple_preprocessing(image_path):
    """Apply comprehensive image preprocessing for MRZ detection"""
    try:
        # Load original image
        photo = cv2.imread(image_path)
        if photo is None:
            raise Exception("Could not load image")
        
        # Upscale image for better OCR accuracy
        photo = cv2.resize(photo, None, fx=2, fy=2, interpolation=cv2.INTER_CUBIC)
        
        # Convert to grayscale
        gray = cv2.cvtColor(photo, cv2.COLOR_BGR2GRAY)
        
        # Apply denoising to reduce artifacts
        denoised = cv2.fastNlMeansDenoising(gray, h=10)
        
        # Apply adaptive thresholding for better text contrast
        thresh = cv2.adaptiveThreshold(
            denoised, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 41, 5
        )
        
        return thresh
        
    except Exception as e:
        logger.error(f"Preprocessing failed: {e}")
        return None

def minimal_preprocessing(image_path):
    """Apply minimal preprocessing - grayscale conversion only"""
    try:
        photo = cv2.imread(image_path)
        if photo is None:
            raise Exception("Could not load image")
        
        # Only convert to grayscale
        gray = cv2.cvtColor(photo, cv2.COLOR_BGR2GRAY)
        
        return gray
        
    except Exception as e:
        logger.error(f"Minimal preprocessing failed: {e}")
        return None

def enhanced_preprocessing(image_path):
    """Apply enhanced preprocessing with contrast enhancement"""
    try:
        photo = cv2.imread(image_path)
        if photo is None:
            raise Exception("Could not load image")
        
        # Apply CLAHE for better contrast
        lab = cv2.cvtColor(photo, cv2.COLOR_BGR2LAB)
        l, a, b = cv2.split(lab)
        
        clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8,8))
        l = clahe.apply(l)
        
        enhanced = cv2.merge([l, a, b])
        enhanced = cv2.cvtColor(enhanced, cv2.COLOR_LAB2BGR)
        
        # Convert to grayscale and apply processing
        gray = cv2.cvtColor(enhanced, cv2.COLOR_BGR2GRAY)
        blurred = cv2.GaussianBlur(gray, (3, 3), 0)
        
        # Apply OTSU thresholding
        _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        return thresh
        
    except Exception as e:
        logger.error(f"Enhanced preprocessing failed: {e}")
        return None

def test_direct_ocr(image_path):
    """Test direct OCR on different image regions for debugging"""
    try:
        image = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
        h, w = image.shape
        
        # Test multiple region sizes to find MRZ
        regions = [
            ("bottom_40", image[int(h*0.6):h, :]),
            ("bottom_50", image[int(h*0.5):h, :]),
            ("bottom_60", image[int(h*0.4):h, :]),
            ("full_image", image)
        ]
        
        best_result = None
        max_mrz_lines = 0
        
        for region_name, region_img in regions:
            # Apply OCR with MRZ-specific configuration
            config = '--psm 7 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<'
            text = ract.image_to_string(region_img, config=config)
            
            lines = [line.strip() for line in text.split('\n') if line.strip()]
            mrz_lines = [line for line in lines if line.count('<') > 5 and len(line) > 25]
            
            result = {
                "region": region_name,
                "raw_text": text,
                "mrz_lines": mrz_lines,
                "line_count": len(mrz_lines)
            }
            
            if len(mrz_lines) > max_mrz_lines:
                max_mrz_lines = len(mrz_lines)
                best_result = result
        
        return best_result or {"raw_text": "", "mrz_lines": [], "line_count": 0}
        
    except Exception as e:
        logger.error(f"OCR test failed: {e}")
        return None

def mrz_score(data_dict):
    """Calculate MRZ quality score based on extracted fields"""
    try:
        # Define required fields with importance weights
        fields = [
            'surname', 'names', 'number', 'date_of_birth', 
            'expiration_date', 'nationality', 'sex', 'personal_number'
        ]
        
        correct = sum(1 for f in fields 
                     if data_dict.get(f) and data_dict[f] != "<<<<<<<<<<<<<<<<")
        
        return int((correct / len(fields)) * 100)
        
    except Exception as e:
        logger.error(f"Scoring error: {e}")
        return 0

def evaluate_mrz_result(mrz, method_name):
    """Evaluate and score a single MRZ detection result"""
    try:
        if mrz is None:
            return {
                "method": method_name,
                "success": False,
                "score": 0,
                "reason": "No MRZ detected"
            }
        
        data = mrz.to_dict()
        our_score = mrz_score(data)
        mrz_valid_score = getattr(mrz, 'valid_score', 0)
        
        # Combine scores for overall ranking
        combined_score = (our_score * 0.7) + (mrz_valid_score * 0.3)
        
        return {
            "method": method_name,
            "success": True,
            "parsed_data": data,
            "our_score": our_score,
            "mrz_valid_score": mrz_valid_score,
            "combined_score": round(combined_score, 2),
            "field_count": sum(1 for field in ['surname', 'names', 'number', 'date_of_birth', 
                                             'expiration_date', 'nationality', 'sex', 'personal_number']
                             if data.get(field) and data[field] != "<<<<<<<<<<<<<<<<"),
            "confidence": "high" if combined_score >= 80 else "medium" if combined_score >= 60 else "low"
        }
        
    except Exception as e:
        return {
            "method": method_name,
            "success": False,
            "score": 0,
            "error": str(e),
            "reason": f"Parsing failed: {str(e)}"
        }

@app.post("/scan_debug")
def scan_mrz_debug(payload: ImagePayload):
    """Enhanced scan that tries multiple methods and picks the best result"""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S_%f")[:-3]
    all_results = []
    
    try:
        logger.info("Starting MRZ detection process")
        
        # Decode and validate image
        image_data = base64.b64decode(payload.image_base64)
        np_arr = np.frombuffer(image_data, np.uint8)
        image = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)
        
        if image is None:
            return {"success": False, "message": "Could not decode image", "method": "decode_failed"}
        
        # Save image to temporary file
        with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as temp_file:
            cv2.imwrite(temp_file.name, image)
            temp_path = temp_file.name
        
        # Method 1: Test original image without any processing
        logger.info("Testing original image")
        try:
            mrz_original = read_mrz(temp_path)
            result_original = evaluate_mrz_result(mrz_original, "original_image")
            all_results.append(result_original)
            logger.info(f"Original image: {'SUCCESS' if result_original['success'] else 'FAILED'} "
                       f"Score: {result_original.get('combined_score', 0)}")
        except Exception as e:
            all_results.append({
                "method": "original_image",
                "success": False,
                "score": 0,
                "error": str(e)
            })
            logger.warning(f"Original image failed: {e}")
        
        # Method 2: Test minimal preprocessing
        logger.info("Testing minimal preprocessing")
        try:
            minimal_img = minimal_preprocessing(temp_path)
            if minimal_img is not None:
                minimal_path = temp_path.replace('.png', '_minimal.png')
                cv2.imwrite(minimal_path, minimal_img)
                mrz_minimal = read_mrz(minimal_path)
                result_minimal = evaluate_mrz_result(mrz_minimal, "minimal_preprocessing")
                all_results.append(result_minimal)
                logger.info(f"Minimal processing: {'SUCCESS' if result_minimal['success'] else 'FAILED'} "
                           f"Score: {result_minimal.get('combined_score', 0)}")
                os.remove(minimal_path)
            else:
                raise Exception("Minimal preprocessing failed")
        except Exception as e:
            all_results.append({
                "method": "minimal_preprocessing",
                "success": False,
                "score": 0,
                "error": str(e)
            })
            logger.warning(f"Minimal preprocessing failed: {e}")
        
        # Method 3: Test full preprocessing
        logger.info("Testing full preprocessing")
        try:
            processed_img = simple_preprocessing(temp_path)
            if processed_img is not None:
                full_path = temp_path.replace('.png', '_full.png')
                cv2.imwrite(full_path, processed_img)
                mrz_full = read_mrz(full_path)
                result_full = evaluate_mrz_result(mrz_full, "full_preprocessing")
                all_results.append(result_full)
                logger.info(f"Full processing: {'SUCCESS' if result_full['success'] else 'FAILED'} "
                           f"Score: {result_full.get('combined_score', 0)}")
                os.remove(full_path)
            else:
                raise Exception("Full preprocessing failed")
        except Exception as e:
            all_results.append({
                "method": "full_preprocessing",
                "success": False,
                "score": 0,
                "error": str(e)
            })
            logger.warning(f"Full preprocessing failed: {e}")
        
        # Method 4: Test enhanced preprocessing
        logger.info("Testing enhanced preprocessing")
        try:
            enhanced_img = enhanced_preprocessing(temp_path)
            if enhanced_img is not None:
                enhanced_path = temp_path.replace('.png', '_enhanced.png')
                cv2.imwrite(enhanced_path, enhanced_img)
                mrz_enhanced = read_mrz(enhanced_path)
                result_enhanced = evaluate_mrz_result(mrz_enhanced, "enhanced_preprocessing")
                all_results.append(result_enhanced)
                logger.info(f"Enhanced processing: {'SUCCESS' if result_enhanced['success'] else 'FAILED'} "
                           f"Score: {result_enhanced.get('combined_score', 0)}")
                os.remove(enhanced_path)
            else:
                raise Exception("Enhanced preprocessing failed")
        except Exception as e:
            all_results.append({
                "method": "enhanced_preprocessing",
                "success": False,
                "score": 0,
                "error": str(e)
            })
            logger.warning(f"Enhanced preprocessing failed: {e}")
        
        # Method 5: OCR fallback if all methods fail
        logger.info("Testing OCR fallback")
        try:
            ocr_result = test_direct_ocr(temp_path)
            if ocr_result and ocr_result.get("line_count", 0) > 0:
                fallback_lines = extract_mrz_fallback(ocr_result["raw_text"])
                if fallback_lines:
                    all_results.append({
                        "method": "ocr_fallback",
                        "success": True,
                        "combined_score": 40,  # Lower score since it's just OCR
                        "mrz_lines": fallback_lines,
                        "raw_text": ocr_result["raw_text"],
                        "confidence": "low"
                    })
                    logger.info(f"OCR fallback: SUCCESS - Found {len(fallback_lines)} lines")
                else:
                    logger.warning("OCR fallback: No valid MRZ lines found")
            else:
                logger.warning("OCR fallback: No text detected")
        except Exception as e:
            logger.warning(f"OCR fallback failed: {e}")
        
        # Clean up temporary files
        if os.path.exists(temp_path):
            os.remove(temp_path)
        
        # Find best result
        successful_results = [r for r in all_results if r.get("success", False)]
        
        if not successful_results:
            logger.warning("No methods succeeded")
            return {
                "success": False,
                "message": "No MRZ found with any method",
                "all_results": all_results,
                "debug_timestamp": timestamp
            }
        
        # Sort by combined score and select best
        successful_results.sort(key=lambda x: x.get("combined_score", 0), reverse=True)
        best_result = successful_results[0]
        
        logger.info(f"BEST RESULT: {best_result['method']} with score {best_result.get('combined_score', 0)}")
        
        # Prepare final response
        response = {
            "success": True,
            "best_method": best_result["method"],
            "best_score": best_result.get("combined_score", 0),
            "confidence": best_result.get("confidence", "unknown"),
            "all_methods_tried": len(all_results),
            "successful_methods": len(successful_results),
            "method_comparison": all_results,
            "debug_timestamp": timestamp
        }
        
        # Add best result's data
        if "parsed_data" in best_result:
            response.update({
                "parsed_data": best_result["parsed_data"],
                "score": best_result.get("our_score", 0),
                "valid_score": best_result.get("mrz_valid_score", 0),
                "valid_mrz": best_result.get("combined_score", 0) > 50,
                "method": best_result["method"]
            })
        elif "mrz_lines" in best_result:
            # OCR fallback case
            response.update({
                "mrz_lines": best_result["mrz_lines"],
                "raw_text": best_result.get("raw_text", ""),
                "method": best_result["method"]
            })
        
        return response
        
    except Exception as e:
        error_trace = traceback.format_exc()
        logger.error(f"Unexpected error: {error_trace}")
        
        return {
            "success": False,
            "message": f"Unexpected error: {str(e)}",
            "method": "unexpected_error",
            "detail": error_trace
        }

@app.post("/scan")
def scan_mrz(payload: ImagePayload):
    """Main scan endpoint"""
    return scan_mrz_debug(payload)

@app.get("/health")
def health_check():
    """Health check endpoint for system status"""
    return {
        "status": "healthy",
        "tesseract_available": tesseract_available,
        "tesseract_path": ract.pytesseract.tesseract_cmd
    }