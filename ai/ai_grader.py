from openai import OpenAI
import pymysql
import datetime
import sys
import re
import json
from dotenv import load_dotenv
import os

load_dotenv()

if len(sys.argv) != 3:
    print("Usage: ai_grader.py <student_id> <quiz_id>")
    sys.exit(1)

student_id = int(sys.argv[1])
quiz_id = int(sys.argv[2])

print(f"Starting AI grading for student_id={student_id}, quiz_id={quiz_id}")

client = OpenAI(
    api_key=os.getenv("OPENAI_API_KEY")
)

db = pymysql.connect(
    host="localhost",
    user="root",
    password="",
    database="takaredu_db",
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor
)

SCORE_MAP = {
    "benar": 3,
    "cukup": 2,
    "kurang": 1,
    "salah": 0
}


def normalize_category(text):
    text = (text or "").strip().lower()
    text = re.sub(r"[^a-zA-Z]", "", text)

    if text in SCORE_MAP:
        return text

    return "salah"


def format_rubric_text(rubric_text):
    rubric_text = (rubric_text or "").strip()

    if not rubric_text:
        return "Tidak ada rubrik khusus. Gunakan kunci jawaban sebagai acuan utama."

    try:
        data = json.loads(rubric_text)

        if isinstance(data, dict):
            return f"""
Rubrik terstruktur:
- Benar (3): {data.get("score_3", "").strip() or "-"}
- Cukup (2): {data.get("score_2", "").strip() or "-"}
- Kurang (1): {data.get("score_1", "").strip() or "-"}
- Salah (0): {data.get("score_0", "").strip() or "-"}
- Catatan tambahan: {data.get("notes", "").strip() or "-"}
"""
    except Exception:
        pass

    return rubric_text


def parse_student_answer(user_answer):
    text_answer = (user_answer or "").strip()
    table_answer = {}

    try:
        parsed_answer = json.loads(text_answer)

        if isinstance(parsed_answer, dict):
            text_answer = str(parsed_answer.get("text_answer", "")).strip()

            if isinstance(parsed_answer.get("table_answer"), dict):
                table_answer = parsed_answer.get("table_answer", {})

    except Exception:
        pass

    return text_answer, table_answer


def parse_table_config(raw_config):
    if not raw_config:
        return {}

    try:
        parsed_config = json.loads(raw_config)

        if isinstance(parsed_config, dict):
            return parsed_config

    except Exception:
        pass

    return {}


def build_rubric_based_table_report(table_config, table_answer):
    if not isinstance(table_config, dict):
        return "Tidak ada tabel jawaban siswa."

    if table_config.get("mode") != "rubric_based":
        return "Tidak ada tabel jawaban siswa."

    input_cells = table_config.get("input_cells", [])

    if not isinstance(input_cells, list) or len(input_cells) == 0:
        return "Tidak ada tabel jawaban siswa."

    lines = []

    for cell in input_cells:
        row = cell.get("row")
        col = cell.get("col")

        key = f"r{row}_c{col}"
        value = str(table_answer.get(key, "")).strip()

        lines.append(
            f"- Sel {key}: {value if value else '(kosong)'}"
        )

    return f"""
Tabel jawaban siswa berbasis rubrik:
{chr(10).join(lines)}

Catatan:
- Nilai tabel tidak dicocokkan dengan kunci angka tetap.
- Gunakan rubrik, kunci jawaban konsep, dan konsistensi data untuk menentukan kategori.
"""


def ask_ai(prompt, system_message):
    response = client.chat.completions.create(
        model="gpt-5-mini",
        messages=[
            {
                "role": "system",
                "content": system_message
            },
            {
                "role": "user",
                "content": prompt
            }
        ],
    )

    return response.choices[0].message.content


def grade_reasoned_multiple_choice(row):
    answer_id = row["answer_id"]
    is_right = row.get("is_right")
    user_answer = (row["answer_text"] or "").strip()
    question_text = row["question"] or ""
    answer_key = row["answer_key_text"] or ""

    rubric_reference = answer_key if answer_key else None

    if int(is_right or 0) != 1:
        return "salah", 0, rubric_reference

    if user_answer == "":
        return "salah", 1, rubric_reference

    prompt = f"""
Anda adalah penilai alasan pada soal pilihan ganda beralasan.

Pilihan siswa sudah BENAR.
Tugas Anda hanya menilai kualitas alasan siswa berdasarkan soal dan kunci alasan.

Soal:
{question_text}

Kunci alasan:
{answer_key}

Alasan siswa:
{user_answer}

Kategori alasan:
- Benar: alasan sesuai konsep utama dan lengkap.
- Kurang: alasan memuat sebagian konsep benar tetapi kurang lengkap atau kurang tepat.
- Salah: alasan tidak sesuai, kosong, tidak relevan, atau konsep keliru.

Balas hanya dengan salah satu kata:
Benar
Kurang
Salah
"""

    try:
        raw_category = ask_ai(
            prompt,
            "Anda adalah evaluator alasan pilihan ganda beralasan. Balas hanya: Benar, Kurang, atau Salah."
        )

        category = normalize_category(raw_category)

        if category == "benar":
            return "benar", 3, rubric_reference

        if category == "kurang":
            return "kurang", 2, rubric_reference

        return "salah", 1, rubric_reference

    except Exception as e:
        print(f"Gagal menilai PGB answer_id={answer_id}: {e}")
        return "salah", 1, rubric_reference


def grade_essay(row):
    answer_id = row["answer_id"]
    user_answer = (row["answer_text"] or "").strip()
    question_text = row["question"] or ""
    answer_key = row["answer_key_text"] or ""
    rubric_text = row["rubric_text"] or ""
    answer_table_config_raw = row.get("answer_table_config") or ""

    rubric_reference = rubric_text.strip() if rubric_text.strip() else None
    formatted_rubric = format_rubric_text(rubric_text)

    text_answer, table_answer = parse_student_answer(user_answer)
    table_config = parse_table_config(answer_table_config_raw)

    if text_answer == "" and not table_answer:
        return "salah", 0, rubric_reference

    prompt = f"""
Anda adalah penilai jawaban uraian siswa.

Tugas Anda menentukan kategori jawaban siswa berdasarkan soal, kunci jawaban, rubrik jika tersedia, jawaban teks siswa, dan jawaban tabel siswa jika ada.

Soal:
{question_text}

Kunci jawaban:
{answer_key}

Rubrik/kriteria penilaian:
{formatted_rubric}

Jawaban teks siswa:
{text_answer if text_answer else "(kosong)"}

Jawaban tabel siswa:
{build_rubric_based_table_report(table_config, table_answer)}

Aturan penilaian:
- Gunakan kunci jawaban sebagai acuan konsep yang benar.
- Gunakan rubrik untuk menentukan tingkat kualitas jawaban.
- Jika ada tabel jawaban siswa, pertimbangkan kelengkapan, kewajaran, dan konsistensi data tabel terhadap jawaban teks siswa.
- Jangan menilai tabel hanya berdasarkan kecocokan angka tetap, kecuali rubrik atau kunci jawaban menyatakan demikian.
- Jika jawaban teks benar tetapi tabel tidak lengkap atau tidak logis, kategori tidak boleh Benar.
- Jika tabel baik tetapi penjelasan konsep keliru, kategori tidak boleh Benar.

Kategori:
- Benar
- Cukup
- Kurang
- Salah

Balas hanya dengan salah satu kata berikut:
Benar
Cukup
Kurang
Salah
"""

    try:
        raw_category = ask_ai(
            prompt,
            "Anda adalah evaluator jawaban uraian. Balas hanya kategori: Benar, Cukup, Kurang, atau Salah."
        )

        category = normalize_category(raw_category)
        score = SCORE_MAP[category]

        return category, score, rubric_reference

    except Exception as e:
        print(f"Gagal menilai essay answer_id={answer_id}: {e}")
        return "salah", 0, rubric_reference


try:
    with db.cursor() as cursor:

        cursor.execute("""
            SELECT
                a.id AS answer_id,
                a.answer_text,
                a.is_right,
                q.question_type,
                q.question,
                q.answer_key_text,
                q.rubric_text,
                q.answer_table_config
            FROM answers a
            INNER JOIN questions q
                ON a.question_id = q.id
            LEFT JOIN answer_evaluations ae
                ON ae.answer_id = a.id
            WHERE a.student_id = %s
            AND a.quiz_id = %s
            AND q.question_type IN ('essay', 'reasoned_multiple_choice')
            AND ae.id IS NULL
        """, (student_id, quiz_id))

        answers = cursor.fetchall()

        print(f"Found {len(answers)} answers to grade.")

        for row in answers:
            answer_id = row["answer_id"]
            question_type = row["question_type"]

            if question_type == "reasoned_multiple_choice":
                category, score, rubric_reference = grade_reasoned_multiple_choice(row)
            else:
                category, score, rubric_reference = grade_essay(row)

            cursor.execute("""
                INSERT INTO answer_evaluations (
                    answer_id,
                    category,
                    score,
                    rubric_reference,
                    created_at
                ) VALUES (
                    %s, %s, %s, %s, NOW()
                )
            """, (
                answer_id,
                category.capitalize(),
                score,
                rubric_reference
            ))

        cursor.execute("""
            SELECT id
            FROM quiz_student_list
            WHERE student_id = %s
            AND quiz_id = %s
            LIMIT 1
        """, (student_id, quiz_id))

        qsl = cursor.fetchone()

        if qsl:
            quiz_student_id = qsl["id"]

            cursor.execute("""
                SELECT
                    COALESCE(SUM(
                        CASE
                            WHEN q.question_type IN ('essay', 'reasoned_multiple_choice')
                                THEN COALESCE(ae.score, 0)

                            WHEN q.question_type IN ('multiple_choice', 'short_answer')
                                AND a.is_right = 1
                                THEN q.points

                            ELSE 0
                        END
                    ), 0) AS final_score,

                    COALESCE(SUM(
                        CASE
                            WHEN q.question_type != 'likert'
                                THEN q.points
                            ELSE 0
                        END
                    ), 0) AS max_score

                FROM answers a
                INNER JOIN questions q
                    ON q.id = a.question_id
                LEFT JOIN answer_evaluations ae
                    ON ae.answer_id = a.id
                WHERE a.student_id = %s
                AND a.quiz_id = %s
            """, (student_id, quiz_id))

            score_data = cursor.fetchone()

            cursor.execute("""
                UPDATE history
                SET
                    final_score = %s,
                    max_score = %s
                WHERE quiz_student_id = %s
                LIMIT 1
            """, (
                score_data["final_score"],
                score_data["max_score"],
                quiz_student_id
            ))

        db.commit()

        print("[OK] AI grading selesai dan history diperbarui.")

        with open("ai_grader_log.txt", "a", encoding="utf-8") as f:
            f.write(
                f"[{datetime.datetime.now()}] "
                f"Graded quiz_id={quiz_id}, student_id={student_id}, total={len(answers)}\n"
            )

except Exception as e:
    db.rollback()
    print(f"[ERROR] {e}")
    sys.exit(1)

finally:
    db.close()