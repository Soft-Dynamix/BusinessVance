import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const all = searchParams.get('all') === 'true';

    const plans = await db.plan.findMany({
      where: all ? {} : { visible: true },
      include: {
        category: true,
        features: { orderBy: { createdAt: 'asc' } },
      },
      orderBy: { displayOrder: 'asc' },
    });

    return NextResponse.json({ plans });
  } catch (error) {
    console.error('Error fetching plans:', error);
    return NextResponse.json({ error: 'Failed to fetch plans' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const {
      name,
      subtitle,
      price,
      color,
      buttonLabel,
      buttonType,
      buttonUrl,
      woocommerceProductId,
      categoryId,
      visible,
      featured,
      displayOrder,
      features,
    } = body;

    const plan = await db.plan.create({
      data: {
        name,
        subtitle: subtitle || '',
        price: parseFloat(price) || 0,
        color: color || '#002B5C',
        buttonLabel: buttonLabel || 'GET STARTED',
        buttonType: buttonType || 'cart',
        buttonUrl: buttonUrl || '',
        woocommerceProductId: woocommerceProductId || '',
        categoryId: categoryId || null,
        visible: visible !== false,
        featured: featured === true,
        displayOrder: parseInt(displayOrder) || 0,
        features: {
          create: (features || []).map((text: string) => ({ text })),
        },
      },
      include: {
        category: true,
        features: { orderBy: { createdAt: 'asc' } },
      },
    });

    return NextResponse.json({ plan }, { status: 201 });
  } catch (error) {
    console.error('Error creating plan:', error);
    return NextResponse.json({ error: 'Failed to create plan' }, { status: 500 });
  }
}

export async function PUT(request: NextRequest) {
  try {
    const body = await request.json();
    const { id, features, ...data } = body;

    if (!id) {
      return NextResponse.json({ error: 'Plan ID is required' }, { status: 400 });
    }

    if (data.price !== undefined) {
      data.price = parseFloat(data.price) || 0;
    }

    if (data.displayOrder !== undefined) {
      data.displayOrder = parseInt(data.displayOrder) || 0;
    }

    // Handle features update: delete old ones and create new ones
    if (features !== undefined) {
      await db.planFeature.deleteMany({ where: { planId: id } });
      data.features = {
        create: (features as string[]).map((text: string) => ({ text })),
      };
    }

    const plan = await db.plan.update({
      where: { id },
      data,
      include: {
        category: true,
        features: { orderBy: { createdAt: 'asc' } },
      },
    });

    return NextResponse.json({ plan });
  } catch (error) {
    console.error('Error updating plan:', error);
    return NextResponse.json({ error: 'Failed to update plan' }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get('id');

    if (!id) {
      return NextResponse.json({ error: 'Plan ID is required' }, { status: 400 });
    }

    await db.plan.delete({ where: { id } });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Error deleting plan:', error);
    return NextResponse.json({ error: 'Failed to delete plan' }, { status: 500 });
  }
}