import { NextRequest, NextResponse } from 'next/server'
import { z } from 'zod'
import { db } from '@/lib/db'

const planUpdateSchema = z.object({
  name: z.string().min(1, 'Name is required').optional(),
  subtitle: z.string().optional(),
  price: z.number().optional(),
  color: z.string().optional(),
  buttonLabel: z.string().optional(),
  buttonType: z.enum(['cart', 'link']).optional(),
  buttonUrl: z.string().optional(),
  woocommerceProductId: z.string().optional(),
  categoryId: z.string().nullable().optional(),
  visible: z.boolean().optional(),
  featured: z.boolean().optional(),
  displayOrder: z.number().int().optional(),
  features: z.array(z.string()).optional(),
})

type RouteContext = { params: Promise<{ id: string }> }

export async function GET(_request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params

    const plan = await db.plan.findUnique({
      where: { id },
      include: {
        category: true,
        features: {
          orderBy: { createdAt: 'asc' },
        },
      },
    })

    if (!plan) {
      return NextResponse.json(
        { error: 'Plan not found' },
        { status: 404 }
      )
    }

    return NextResponse.json(plan)
  } catch (error) {
    console.error('Error fetching plan:', error)
    return NextResponse.json(
      { error: 'Failed to fetch plan' },
      { status: 500 }
    )
  }
}

export async function PUT(request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params
    const body = await request.json()
    const parsed = planUpdateSchema.parse(body)

    const { features, ...planData } = parsed

    // If features array is provided, delete old and create new
    if (features !== undefined) {
      await db.planFeature.deleteMany({ where: { planId: id } })
    }

    const plan = await db.plan.update({
      where: { id },
      data: {
        ...planData,
        ...(features !== undefined && {
          features: {
            create: features.map((text) => ({ text })),
          },
        }),
      },
      include: {
        category: true,
        features: {
          orderBy: { createdAt: 'asc' },
        },
      },
    })

    return NextResponse.json(plan)
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json(
        { error: 'Validation failed', details: error.errors },
        { status: 400 }
      )
    }
    console.error('Error updating plan:', error)
    return NextResponse.json(
      { error: 'Failed to update plan' },
      { status: 500 }
    )
  }
}

export async function DELETE(_request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params

    await db.plan.delete({
      where: { id },
    })

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error('Error deleting plan:', error)
    return NextResponse.json(
      { error: 'Failed to delete plan' },
      { status: 500 }
    )
  }
}