#!/usr/bin/env python3
"""
Extractor de PDFs de fondos de pensiones colombianos
Soporta: Colpensiones, Skandia, Porvenir, Colfondos, Protección
"""

import sys
import json
import re
import pdfplumber
from unicodedata import normalize
from typing import Optional
from datetime import datetime


def limpiar_texto(texto: str) -> str:
    """Normaliza texto para comparar encabezados (sin tildes, mayúsculas, sin saltos)."""
    if texto is None:
        return ""
    texto = str(texto).replace("\n", " ").strip()
    texto = normalize("NFKD", texto).encode("ascii", "ignore").decode("ascii")
    return texto.upper()


def limpiar_monto(valor: str) -> int:
    """Convierte '$ 3.500.000' o '3,500,000' a entero 3500000."""
    if valor is None:
        return 0
    valor = str(valor)
    valor = valor.replace("$", "").replace(" ", "").replace(".", "").replace(",", "")
    valor = re.sub(r"[^0-9]", "", valor)
    return int(valor) if valor else 0


# ============================================================================
# PORVENIR - Extracción por tabla
# ============================================================================
def encontrar_indices_porvenir(fila_encabezado):
    """Busca índices de MES e IBC"""
    idx_mes = idx_ibc = None
    for i, celda in enumerate(fila_encabezado):
        t = limpiar_texto(celda)
        if t == "MES":
            idx_mes = i
        if "IBC" in t or "INGRESO BASE" in t or "COTIZ" in t:
            idx_ibc = i
    return idx_mes, idx_ibc


def extraer_porvenir(pdf_path: str):
    """Extrae datos de Porvenir"""
    registros = []

    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages, start=1):
            tablas = page.extract_tables()
            if not tablas:
                continue

            for tabla in tablas:
                if not tabla or len(tabla) < 2:
                    continue

                header_candidates = tabla[:3] if len(tabla) >= 3 else tabla[:1]
                idx_mes = idx_ibc = None
                header_row = None

                for cand in header_candidates:
                    im, ii = encontrar_indices_porvenir(cand)
                    if im is not None and ii is not None:
                        idx_mes, idx_ibc = im, ii
                        header_row = cand
                        break

                if idx_mes is None or idx_ibc is None:
                    continue

                start_idx = tabla.index(header_row) + 1 if header_row in tabla else 1
                for fila in tabla[start_idx:]:
                    if not fila or len(fila) <= max(idx_mes, idx_ibc):
                        continue

                    mes_raw = (fila[idx_mes] or "").strip()
                    ibc_raw = (fila[idx_ibc] or "").strip()

                    # Validar mes tipo 1995/07 o 2024/05
                    if not re.match(r"^\d{4}/\d{2}$", mes_raw):
                        continue

                    ibc = limpiar_monto(ibc_raw)
                    if ibc == 0:
                        continue

                    # Convertir 1995/07 a 199507
                    periodo = mes_raw.replace("/", "")

                    registros.append({
                        "periodo": periodo,
                        "salario": ibc,
                    })

    return registros


# ============================================================================
# COLFONDOS - Extracción por tabla (empieza página 7)
# ============================================================================
def encontrar_indices_colfondos(header_row):
    """Ubica las posiciones de PERIODO COTIZADO y SALARIO BASE DE COTIZACION"""
    idx_periodo = idx_salario = None
    for i, cell in enumerate(header_row):
        t = limpiar_texto(cell)
        if ("PERIODO" in t and "COTIZADO" in t) or ("PERIODO COTIZADO" in t):
            idx_periodo = i
        if ("SALARIO" in t and "BASE" in t and "COTIZ" in t):
            idx_salario = i
    return idx_periodo, idx_salario


def extraer_colfondos(pdf_path: str):
    """Extrae datos de Colfondos - historia laboral empieza página 7"""
    registros = []

    with pdfplumber.open(pdf_path) as pdf:
        # Empezar desde página 7 (índice 6)
        start_page = min(6, len(pdf.pages) - 1)

        for page in pdf.pages[start_page:]:
            tables = page.extract_tables()
            if not tables:
                continue

            for table in tables:
                if not table or len(table) < 2:
                    continue

                # Probar primeras 3 filas como header
                header_candidates = table[:3] if len(table) >= 3 else table[:1]
                idx_periodo = idx_salario = None
                header_row = None

                for cand in header_candidates:
                    ip, isal = encontrar_indices_colfondos(cand)
                    if ip is not None and isal is not None:
                        idx_periodo, idx_salario = ip, isal
                        header_row = cand
                        break

                # Si no encontramos, probar primera fila "a la brava"
                if idx_periodo is None or idx_salario is None:
                    ip, isal = encontrar_indices_colfondos(table[0])
                    if ip is not None and isal is not None:
                        idx_periodo, idx_salario = ip, isal
                        header_row = table[0]

                if idx_periodo is None or idx_salario is None:
                    continue

                # Recorrer filas de datos
                start_idx = table.index(header_row) + 1 if header_row in table else 1
                for row in table[start_idx:]:
                    if not row:
                        continue
                    if len(row) <= max(idx_periodo, idx_salario):
                        continue

                    periodo_raw = (row[idx_periodo] or "").strip()
                    salario_raw = (row[idx_salario] or "").strip()

                    # Periodo esperado como AAAAMM (ej. 201611)
                    periodo_digits = re.sub(r"\D", "", periodo_raw)
                    if not re.fullmatch(r"\d{6}", periodo_digits):
                        continue

                    salario = limpiar_monto(salario_raw)
                    if salario == 0:
                        continue

                    registros.append({
                        "periodo": periodo_digits,
                        "salario": salario,
                    })

    return registros


# ============================================================================
# SKANDIA - Extracción por regex sobre texto
# ============================================================================
RE_PERIODO_SKANDIA = re.compile(r'(19|20)\d{2}[/-]?\d{2}')
RE_MONTO_SEP = re.compile(r'\$?\s*[0-9]{1,3}(?:[.,][0-9]{3})+(?:[.,][0-9]{2})?')
RE_MONTO_PLAIN = re.compile(r'\$?\s*[0-9]{4,}')


def normalizar_periodo_skandia(s):
    """Normaliza periodo y devuelve (periodo_YYYYMM, end_index)"""
    m = RE_PERIODO_SKANDIA.search(s)
    if not m:
        return None, None
    span = m.span()
    raw = m.group(0).replace('-', '').replace('/', '')
    yyyy, mm = raw[:4], raw[4:]
    try:
        if not (1 <= int(mm) <= 12):
            return None, None
    except:
        return None, None
    return f"{yyyy}{mm}", span[1]


def monto_a_entero_skandia(txt):
    """Convierte monto a entero"""
    if not txt:
        return 0
    s = str(txt).strip()

    if '.' in s and ',' in s:
        if s.rfind(',') > s.rfind('.'):
            s = s.replace('.', '').replace(',', '.')
        else:
            s = s.replace(',', '')
    else:
        s = s.replace(',', '')

    s = s.replace('$', '').strip()

    try:
        return int(round(float(s)))
    except:
        s = re.sub(r'[^0-9]', '', s)
        return int(s) if s else 0


def extraer_skandia(pdf_path: str):
    """Extrae datos de Skandia usando regex"""
    registros = []

    with pdfplumber.open(pdf_path) as pdf:
        for pageno, page in enumerate(pdf.pages, start=1):
            text = page.extract_text() or ""
            for line in text.splitlines():
                periodo, end_idx = normalizar_periodo_skandia(line)
                if not periodo:
                    continue

                # Buscar PRIMER MONTO DESPUÉS del período
                tail = line[end_idx:]
                m = RE_MONTO_SEP.search(tail)
                if not m:
                    m = RE_MONTO_PLAIN.search(tail)
                if not m:
                    continue

                salario_raw = m.group(0)
                salario = monto_a_entero_skandia(salario_raw)
                if salario <= 0:
                    continue

                registros.append({
                    "periodo": periodo,
                    "salario": salario,
                })

    return registros


# ============================================================================
# PROTECCIÓN - Extracción por regex sobre texto
# ============================================================================
def extraer_proteccion(pdf_path: str):
    """Extrae datos de Protección usando regex sobre texto"""
    registros = []

    with pdfplumber.open(pdf_path) as pdf:
        for pageno, page in enumerate(pdf.pages, start=1):
            text = page.extract_text() or ""
            for line in text.splitlines():
                # Buscar mes y el primer monto que aparezca a continuación
                m = re.search(r'(\d{4}/\d{2})\s+\$?([\d\.,]+)', line)
                if not m:
                    continue
                mes = m.group(1)
                monto_str = m.group(2)
                ibc = int(monto_str.replace(".", "").replace(",", ""))

                # Convertir 2003/01 a 200301
                periodo = mes.replace("/", "")

                registros.append({
                    "periodo": periodo,
                    "salario": ibc,
                })

    return registros


# ============================================================================
# COLPENSIONES - Extracción por tabla (sin días cotizados)
# ============================================================================
def encontrar_indices_colpensiones(header_row):
    """Busca PERIODO e IBC/REPORTADO"""
    idx_periodo = idx_salario = None
    for i, cell in enumerate(header_row):
        t = limpiar_texto(cell)
        if "PERIODO" in t or "PERIOD" in t:
            idx_periodo = i
        if "IBC" in t or "REPORTADO" in t or "INGRESO" in t or "BASE" in t:
            idx_salario = i
    return idx_periodo, idx_salario


def extraer_colpensiones(pdf_path: str):
    """Extrae datos de Colpensiones (sin días cotizados)"""
    registros = []

    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            tables = page.extract_tables()
            if not tables:
                continue

            for table in tables:
                if not table or len(table) < 2:
                    continue

                header_candidates = table[:3] if len(table) >= 3 else table[:1]
                idx_periodo = idx_salario = None
                header_row = None

                for cand in header_candidates:
                    ip, isal = encontrar_indices_colpensiones(cand)
                    if ip is not None and isal is not None:
                        idx_periodo, idx_salario = ip, isal
                        header_row = cand
                        break

                if idx_periodo is None or idx_salario is None:
                    continue

                start_idx = table.index(header_row) + 1 if header_row in table else 1
                for row in table[start_idx:]:
                    if not row or len(row) <= max(idx_periodo, idx_salario):
                        continue

                    periodo_raw = (row[idx_periodo] or "").strip()
                    salario_raw = (row[idx_salario] or "").strip()

                    # Validar formato YYYYMM
                    if not re.fullmatch(r"\d{6}", periodo_raw):
                        continue

                    salario = limpiar_monto(salario_raw)
                    if salario == 0:
                        continue

                    registros.append({
                        "periodo": periodo_raw,
                        "salario": salario,
                    })

    return registros


# ============================================================================
# FUNCIÓN PRINCIPAL
# ============================================================================
def extraer_pdf(pdf_path: str, fondo: str):
    """Extrae datos del PDF según el fondo especificado"""
    fondo = fondo.lower()

    extractores = {
        "porvenir": extraer_porvenir,
        "colfondos": extraer_colfondos,
        "skandia": extraer_skandia,
        "proteccion": extraer_proteccion,
        "colpensiones": extraer_colpensiones,
    }

    if fondo not in extractores:
        return {
            "success": False,
            "error": f"Fondo '{fondo}' no soportado. Opciones: {', '.join(extractores.keys())}"
        }

    try:
        registros = extractores[fondo](pdf_path)

        # Eliminar duplicados y consolidar
        registros_por_periodo = {}
        for reg in registros:
            periodo = reg["periodo"]
            if periodo not in registros_por_periodo:
                registros_por_periodo[periodo] = {"salario": reg["salario"]}
            else:
                # Si hay múltiples registros del mismo período, sumar
                registros_por_periodo[periodo]["salario"] += reg["salario"]

        # Convertir a lista y ordenar
        data = sorted(
            [{"periodo": p, "salario": info["salario"]}
             for p, info in registros_por_periodo.items()],
            key=lambda x: x["periodo"]
        )

        # Calcular semanas: 4.285714 semanas por mes
        VALOR_MES = 360 / 7 / 12  # 4.285714
        total_semanas = len(data) * VALOR_MES

        return {
            "success": True,
            "fondo": fondo,
            "total_rows": len(data),
            "semanas": total_semanas,
            "data": data,
            "extracted_at": datetime.now().isoformat()
        }

    except FileNotFoundError:
        return {
            "success": False,
            "error": f"Archivo no encontrado: {pdf_path}"
        }
    except Exception as e:
        return {
            "success": False,
            "error": f"Error al procesar PDF: {str(e)}"
        }


def main():
    if len(sys.argv) != 3:
        print(json.dumps({
            "success": False,
            "error": "Uso: python3 extract_pdf.py <ruta_pdf> <fondo>"
        }))
        sys.exit(1)

    pdf_path = sys.argv[1]
    fondo = sys.argv[2]

    resultado = extraer_pdf(pdf_path, fondo)

    # Imprimir JSON para que PHP lo capture
    print(json.dumps(resultado, ensure_ascii=False, indent=2))

    # Exit code según éxito
    sys.exit(0 if resultado["success"] else 1)


if __name__ == "__main__":
    main()
