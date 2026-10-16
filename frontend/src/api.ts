import axios from 'axios'

export const api = axios.create({
  baseURL: `${import.meta.env.VITE_API_URL.replace(/\/$/, '')}/api`,
  headers: { Accept: 'application/json' },
})

export interface Image {
  id: number
  original_name: string
  thumbnail_url: string
  download_url: string
  extension: string
  size: number
  width: number
  height: number
  uploader_name: string
  temperature: number | null
  created_at: string
}

export interface ImagePage {
  data: Image[]
  meta: {
    next_cursor: string | null
  }
}

export const imagesQueryKey = ['images'] as const

export async function fetchImages(cursor: string | null): Promise<ImagePage> {
  const { data } = await api.get<ImagePage>('/images', { params: cursor ? { cursor } : {} })

  return data
}

export async function deleteImage(id: number): Promise<void> {
  try {
    await api.delete(`/images/${id}`)
  } catch (error) {
    // Already deleted (e.g. in another tab) - the goal is reached.
    if (axios.isAxiosError(error) && error.response?.status === 404) return
    throw error
  }
}

export type UploadField = 'file' | 'name' | 'email'

export async function uploadImage(
  input: { file: File; name: string; email: string },
  onProgress: (percent: number) => void,
): Promise<Image> {
  const form = new FormData()
  form.append('file', input.file)
  form.append('name', input.name)
  form.append('email', input.email)

  const { data } = await api.post<{ data: Image }>('/images', form, {
    onUploadProgress: (event) => {
      if (event.total) onProgress(Math.round((event.loaded / event.total) * 100))
    },
  })

  return data.data
}

/** Field errors from a 422 response, or null for any other error. */
export function validationErrors(error: unknown): Partial<Record<UploadField, string>> | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) return null

  const errors: Record<string, string[]> = error.response.data?.errors ?? {}

  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

/** Human-readable message for errors that are not tied to a form field. */
export function errorMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) return 'Wystąpił nieoczekiwany błąd.'

  if (!error.response) return 'Brak połączenia z serwerem. Sprawdź połączenie i spróbuj ponownie.'

  if (error.response.status === 429) {
    const seconds = Number(error.response.headers['retry-after'])

    return Number.isFinite(seconds) && seconds > 0
      ? `Zbyt wiele żądań. Spróbuj ponownie za ${seconds} s.`
      : 'Zbyt wiele żądań. Spróbuj ponownie za chwilę.'
  }

  if (error.response.status === 413) return 'Plik jest za duży.'

  return error.response.data?.message || 'Serwer zwrócił błąd. Spróbuj ponownie później.'
}
