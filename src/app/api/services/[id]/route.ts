import { NextRequest, NextResponse } from 'next/server'
import { z } from 'zod'
import { db } from '@/lib/db'

const serviceUpdateSchema = z.object({
  name: z.string().min(1, 'Name is required').optional(),
  slug: z.string().optional(),
  description: z.string().optional(),
  price: z.number().optional(),
  icon: z.string().optional(),
  buttonLabel: z.string().optional(),
  buttonType: z.enum(['cart', 'quote', 'booking', 'link']).optional(),
  buttonUrl: z.string().optional(),
  woocommerceProductId: z.string().optional(),
  categoryId: z.string().nullable().optional(),
  visible: z.boolean().optional(),
  featured: z.boolean().optional(),
  displayOrder: z.number().int().optional(),
})

type RouteContext = { params: Promise<{ id: string }> }

export async function GET(_request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params

    const service = await db.service.findUnique({
      where: { id },
      include: { category: true },
    })

    if (!service) {
      return NextResponse.json(
        { error: 'Service not found' },
        { status: 404 }
      )
    }

    return NextResponse.json(service)
  } catch (error) {
    console.error('Error fetching service:', error)
    return NextResponse.json(
      { error: 'Failed to fetch service' },
      { status: 500 }
    )
  }
}

export async function PUT(request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params
    const body = await request.json()
    const parsed = serviceUpdateSchema.parse(body)

    const service = await db.service.update({
      where: { id },
      data: parsed,
      include: { category: true },
    })

    return NextResponse.json(service)
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json(
        { error: 'Validation failed', details: error.errors },
        { status: 400 }
      )
    }
    console.error('Error updating service:', error)
    return NextResponse.json(
      { error: 'Failed to update service' },
      { status: 500 }
    )
  }
}

export async function DELETE(_request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params

    await db.service.delete({
      where: { id },
    })

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error('Error deleting service:', error)
    return NextResponse.json(
      { error: 'Failed to delete service' },
      { status: 500 }
    )
  }
}