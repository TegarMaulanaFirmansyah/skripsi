from flask import Flask, request, jsonify
from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer
from googletrans import Translator

app = Flask(__name__)
translator = Translator()
analyzer = SentimentIntensityAnalyzer()

DEFAULT_TEXT = "Saya merasa netral tentang topik ini."


@app.route("/analyze", methods=["POST"])
def analyze():
    try:
        data = request.get_json(force=True, silent=True) or {}
        text = data.get("text", "") if isinstance(data, dict) else ""
        text = text.strip() or DEFAULT_TEXT

        translated_text = text
        try:
            translation = translator.translate(text, src="id", dest="en")
            translated_text = translation.text or text
        except Exception:
            # Jika translate gagal, tetap gunakan teks asli
            translated_text = text

        scores = analyzer.polarity_scores(translated_text)
        compound = scores.get("compound", 0.0)

        if compound > 0.05:
            label = "positive"
        elif compound < -0.05:
            label = "negative"
        else:
            label = "neutral"

        response = {
            "original_text": text,
            "translated_text": translated_text,
            "scores": {
                "neg": scores.get("neg", 0.0),
                "neu": scores.get("neu", 0.0),
                "pos": scores.get("pos", 0.0),
                "compound": compound,
            },
            "label": label,
        }

        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "error": "Sentiment analysis failed.",
            "message": str(e),
        }), 500


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
