/** Nome do arquivo no `Content-Disposition` (`attachment; filename="x.pdf"`). */
export function filenameFrom(disposition: string | undefined, fallback: string): string {
  const match = /filename="?([^";]+)"?/i.exec(disposition ?? '')

  return match?.[1] ?? fallback
}

/** Salva um arquivo baixado pela API (com cookie de sessão) como download do navegador. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.append(link)
  link.click()
  link.remove()
  // Dá tempo do navegador começar o download antes de liberar a memória.
  setTimeout(() => URL.revokeObjectURL(url), 0)
}
