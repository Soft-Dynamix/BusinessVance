import { NextRequest, NextResponse } from 'next/server'
import { z } from 'zod'
import { db } from '@/lib/db'

const reorderSchema = z.object({
  items: z.array(
    z.object({
      id: z.string(),
      displayOrder: z.number().int(),
    })
  ),
})

export async function PUT(request: NextRequest) {
  try {
    const body = await request.json()
    const { items } = reorderSchema.parse(body)

    await Promise.all(
      items.map((item) =>
        db.plan.update({
          where: { id: item.id },
          data: { displayOrder: item.displayOrder },
        })
      )
    )

    return NextResponse.json({ success: true })
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json(
        { error: 'Validation failed', details: error.errors },
        { status: 400 }
      )
    }
    console.error('Error reordering plans:', error)
    return NextResponse.json(
      { error: 'Failed to reorder plans' },
      { status: 500 }
    )
  }
}