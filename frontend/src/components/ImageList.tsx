import { useInfiniteQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useInView } from 'react-intersection-observer'
import { fetchImages, imagesQueryKey } from '../api'
import ImageCard from './ImageCard'

export default function ImageList() {
  const { data, error, fetchNextPage, hasNextPage, isFetchingNextPage, isPending, refetch } = useInfiniteQuery({
    queryKey: imagesQueryKey,
    queryFn: ({ pageParam }) => fetchImages(pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
  })

  // Sentinel below the grid: loads the next page shortly before it scrolls into view.
  const { ref: sentinelRef, inView } = useInView({ rootMargin: '400px' })

  useEffect(() => {
    if (inView && hasNextPage && !isFetchingNextPage && !error) {
      fetchNextPage()
    }
  }, [inView, hasNextPage, isFetchingNextPage, error, fetchNextPage])

  if (isPending) {
    return <p aria-busy="true">Wczytywanie plików…</p>
  }

  const images = data?.pages.flatMap((page) => page.data) ?? []

  if (error && images.length === 0) {
    return (
      <p className="notice error">
        Nie udało się pobrać listy plików. <button onClick={() => refetch()}>Spróbuj ponownie</button>
      </p>
    )
  }

  if (images.length === 0) {
    return <p className="notice">Nie ma jeszcze żadnych plików. Prześlij pierwszy!</p>
  }

  return (
    <>
      <div className="image-grid">
        {images.map((image) => (
          <ImageCard key={image.id} image={image} />
        ))}
      </div>

      <div ref={sentinelRef} className="list-footer">
        {isFetchingNextPage && <p aria-busy="true">Wczytywanie kolejnych plików…</p>}
        {error && (
          <p className="notice error">
            Nie udało się wczytać kolejnych plików. <button onClick={() => fetchNextPage()}>Spróbuj ponownie</button>
          </p>
        )}
        {!hasNextPage && <p className="muted">To już wszystkie pliki ({images.length}).</p>}
      </div>
    </>
  )
}
