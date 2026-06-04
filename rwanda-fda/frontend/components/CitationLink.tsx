import Link from 'next/link'

const CITATION_RE = /\[(\w+)\s+([\w_]+):([\w-]+)\]/g

function linkPath(objectType: string, id: string): string | null {
  const t = objectType.toLowerCase()
  if (t === 'recall' || t === 'productrecall') return `/recalls/${id}`
  if (t === 'batch') return `/batches/${id}`
  return null
}

export default function CitationLink({ text }: { text: string }) {
  const parts: React.ReactNode[] = []
  let lastIndex = 0
  const regex = new RegExp(CITATION_RE.source, 'g')
  let match: RegExpExecArray | null

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) parts.push(text.slice(lastIndex, match.index))
    const [full, objectType, , id] = match
    const path = linkPath(objectType, id)
    parts.push(
      path ? (
        <Link
          key={match.index}
          href={path}
          className="text-blue-600 underline font-medium hover:text-blue-800"
        >
          {full}
        </Link>
      ) : (
        <span key={match.index} className="font-medium text-gray-700">
          {full}
        </span>
      )
    )
    lastIndex = match.index + full.length
  }
  if (lastIndex < text.length) parts.push(text.slice(lastIndex))

  return <>{parts}</>
}
