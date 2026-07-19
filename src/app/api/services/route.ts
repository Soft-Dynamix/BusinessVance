import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const all = searchParams.get('all') === 'true';

    const services = await db.service.findMany({
      where: all ? {} : { visible: true },
      include: { category: true },
      orderBy: { displayOrder: 'asc' },
    });

    return NextResponse.json({ services });
  } catch (error) {
    console.error('Error fetching services:', error);
    return NextResponse.json({ error: 'Failed to fetch services' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const {
      name,
      description,
      price,
      icon,
      buttonLabel,
      buttonType,
      buttonUrl,
      woocommerceProductId,
      categoryId,
      visible,
      featured,
      displayOrder,
    } = body;

    const slug = name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');

    const service = await db.service.create({
      data: {
        name,
        slug,
        description,
        price: parseFloat(price) || 0,
        icon: icon || 'FileText',
        buttonLabel: buttonLabel || 'ADD TO CART',
        buttonType: buttonType || 'cart',
        buttonUrl: buttonUrl || '',
        woocommerceProductId: woocommerceProductId || '',
        categoryId: categoryId || null,
        visible: visible !== false,
        featured: featured === true,
        displayOrder: parseInt(displayOrder) || 0,
      },
      include: { category: true },
    });

    return NextResponse.json({ service }, { status: 201 });
  } catch (error) {
    console.error('Error creating service:', error);
    return NextResponse.json({ error: 'Failed to create service' }, { status: 500 });
  }
}

export async function PUT(request: NextRequest) {
  try {
    const body = await request.json();
    const { id, ...data } = body;

    if (!id) {
      return NextResponse.json({ error: 'Service ID is required' }, { status: 400 });
    }

    if (data.name) {
      data.slug = data.name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
    }

    if (data.price !== undefined) {
      data.price = parseFloat(data.price) || 0;
    }

    if (data.displayOrder !== undefined) {
      data.displayOrder = parseInt(data.displayOrder) || 0;
    }

    const service = await db.service.update({
      where: { id },
      data,
      include: { category: true },
    });

    return NextResponse.json({ service });
  } catch (error) {
    console.error('Error updating service:', error);
    return NextResponse.json({ error: 'Failed to update service' }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get('id');

    if (!id) {
      return NextResponse.json({ error: 'Service ID is required' }, { status: 400 });
    }

    await db.service.delete({ where: { id } });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Error deleting service:', error);
    return NextResponse.json({ error: 'Failed to delete service' }, { status: 500 });
  }
}