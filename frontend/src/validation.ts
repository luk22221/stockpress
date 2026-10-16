// Mirrors the API rules for quick feedback; the API remains the source of truth.
export const MAX_FILE_SIZE = 5 * 1024 * 1024
export const MIN_DIMENSION = 500
export const ACCEPT = '.jpg,.jpeg,.png,.webp,.tif,.tiff,.bmp,image/jpeg,image/png,image/webp,image/tiff,image/bmp'

const ALLOWED_EXTENSION = /\.(jpe?g|png|webp|tiff?|bmp)$/i
const TIFF = /\.tiff?$/i

export async function validateFile(file: File): Promise<string | null> {
  if (!ALLOWED_EXTENSION.test(file.name)) {
    return 'Dozwolone typy plików: JPG, PNG, WebP, TIFF, BMP.'
  }

  if (file.size > MAX_FILE_SIZE) {
    return 'Plik może mieć maksymalnie 5 MB.'
  }

  // Browsers cannot decode TIFF - its resolution is checked by the API only.
  if (TIFF.test(file.name)) {
    return null
  }

  try {
    const bitmap = await createImageBitmap(file)
    const tooSmall = bitmap.width < MIN_DIMENSION || bitmap.height < MIN_DIMENSION
    bitmap.close()

    return tooSmall ? `Obraz musi mieć co najmniej ${MIN_DIMENSION} × ${MIN_DIMENSION} px.` : null
  } catch {
    // Not decodable here - let the API decide.
    return null
  }
}
