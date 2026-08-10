#!/usr/bin/env python3
import csv
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import unicodedata

import uno
from com.sun.star.beans import PropertyValue


def prop(name, value):
    item = PropertyValue()
    item.Name = name
    item.Value = value
    return item


def normalize(value):
    return ''.join(char for char in unicodedata.normalize('NFD', value.upper()) if unicodedata.category(char) != 'Mn')


def resource_type(group):
    normalized = normalize(group)
    if 'EQUIP' in normalized:
        return 'Equipamento'
    if 'SERVIC' in normalized or 'MAO DE OBRA' in normalized:
        return 'Servico'
    return 'Material'


def rows(sheet, kind):
    cursor = sheet.createCursor()
    cursor.gotoEndOfUsedArea(True)
    last_row = cursor.RangeAddress.EndRow
    last_column = 15 if kind == 'input' else 24
    cell_range = sheet.getCellRangeByPosition(0, 10, last_column, last_row)
    data = cell_range.getDataArray()
    formulas = cell_range.getFormulaArray()
    for values, row_formulas in zip(data, formulas):
        if kind == 'input':
            group, code, description, unit, origin = values[:5]
            price = '' if row_formulas[15] == '' else values[15]
            item_type = resource_type(group)
        else:
            group, code, description, unit = values[:4]
            origin = 'SINAPI'
            price = '' if row_formulas[24] == '' else values[24]
            item_type = 'Servico'
        group = str(group).strip()
        code = str(int(code) if isinstance(code, float) and code.is_integer() else code).strip()
        description = str(description).strip()
        unit = str(unit).strip()
        if not code or not description:
            continue
        price = '' if price == '' else format(float(price), '.4f').rstrip('0').rstrip('.')
        yield [item_type, code, description, unit, price, group, origin]


def main():
    if len(sys.argv) != 3:
        raise SystemExit('Uso: extract-sinapi.py <referencia.xlsx> <catalogo.csv>')
    source, target = map(os.path.abspath, sys.argv[1:])
    profile = tempfile.mkdtemp(prefix='iasd-lo-')
    port = 20000 + (os.getpid() % 20000)
    process = subprocess.Popen([
        'libreoffice', '--headless', '--nologo', '--nodefault', '--nofirststartwizard',
        f'-env:UserInstallation=file://{profile}',
        f'--accept=socket,host=127.0.0.1,port={port};urp;StarOffice.ComponentContext'
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    document = None
    try:
        local = uno.getComponentContext()
        resolver = local.ServiceManager.createInstanceWithContext('com.sun.star.bridge.UnoUrlResolver', local)
        context = None
        for _ in range(60):
            try:
                context = resolver.resolve(f'uno:socket,host=127.0.0.1,port={port};urp;StarOffice.ComponentContext')
                break
            except Exception:
                time.sleep(0.25)
        if context is None:
            raise RuntimeError('LibreOffice nao iniciou para leitura da referencia.')
        desktop = context.ServiceManager.createInstanceWithContext('com.sun.star.frame.Desktop', context)
        document = desktop.loadComponentFromURL(uno.systemPathToFileUrl(source), '_blank', 0, (prop('Hidden', True), prop('ReadOnly', True)))
        if document is None:
            raise RuntimeError('Nao foi possivel abrir a planilha SINAPI.')
        sheets = document.getSheets()
        with open(target, 'w', newline='', encoding='utf-8') as output:
            writer = csv.writer(output)
            writer.writerow(['tipo', 'codigo', 'descricao', 'unidade', 'preco_mg', 'grupo', 'origem'])
            writer.writerows(rows(sheets.getByName('ISD'), 'input'))
            writer.writerows(rows(sheets.getByName('CSD'), 'composition'))
    finally:
        if document is not None:
            document.close(True)
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
        shutil.rmtree(profile, ignore_errors=True)


if __name__ == '__main__':
    main()
